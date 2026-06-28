// /api_engine/routes/v1/adminRoutes.js
const express = require('express');
const router = express.Router();
// We require the bouncer to keep non-admins out, and our DB/Redis connections
const { verifyAdmin } = require('../../middleware/auth');
const { dbPool, redisClient } = require('../../app');

router.get('/dashboard-stats', verifyAdmin, async (req, res) => {
    try {
        // 1. Generate today's date for the KSA timezone
        const today = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Riyadh' });
        const cacheKey = `admin_dashboard_stats_${today}`;

        // ==========================================
        // 2. CHECK REDIS CACHE FIRST
        // ==========================================
        // If the data is in Redis, return it instantly and stop executing
        if (redisClient.isReady) {
            const cachedStats = await redisClient.get(cacheKey);
            if (cachedStats) {
                return res.status(200).json({
                    status: 'success',
                    message: 'Data loaded from blazing fast Redis cache',
                    source: 'redis',
                    data: JSON.parse(cachedStats)
                });
            }
        }

        // ==========================================
        // 3. IF NO CACHE, QUERY MYSQL IN PARALLEL
        // ==========================================
        // Promise.all fires every query simultaneously. This is massively faster than PHP.
        const [
            [totalAgentsRes], [totalStoresRes], [totalRegionsRes], 
            [completedAssignmentsRes], [totalAssignmentsRes], [totalSubmissionsRes],
            [agentsAssignedRes], [totalStoresTodayRes], [completedStoresTodayRes],
            [totalMallsRes], [completedMallsRes], [totalSubmissionsTodayRes],
            [totalCollectedRes]
        ] = await Promise.all([
            dbPool.query("SELECT COUNT(*) as count FROM users WHERE role = 'agent'"),
            dbPool.query("SELECT COUNT(*) as count FROM stores"),
            dbPool.query("SELECT COUNT(*) as count FROM regions"),
            dbPool.query("SELECT COUNT(*) as count FROM daily_assignments WHERE status = 'completed'"),
            dbPool.query("SELECT COUNT(*) as count FROM daily_assignments"),
            dbPool.query("SELECT COUNT(*) as count FROM bank_submissions"),
            dbPool.query("SELECT COUNT(DISTINCT agent_id) as count FROM daily_assignments WHERE DATE(date_assigned) = ?", [today]),
            dbPool.query("SELECT COUNT(*) as count FROM daily_assignments WHERE DATE(date_assigned) = ?", [today]),
            dbPool.query("SELECT COUNT(*) as count FROM daily_assignments WHERE DATE(date_assigned) = ? AND status = 'completed'", [today]),
            dbPool.query("SELECT COUNT(DISTINCT s.mall) as count FROM daily_assignments da JOIN stores s ON da.store_id = s.id WHERE DATE(da.date_assigned) = ?", [today]),
            dbPool.query("SELECT COUNT(DISTINCT s.mall) as count FROM daily_assignments da JOIN stores s ON da.store_id = s.id WHERE DATE(da.date_assigned) = ? AND da.status = 'completed'", [today]),
            dbPool.query("SELECT COUNT(*) as count FROM bank_submissions WHERE DATE(created_at) = ?", [today]),
            dbPool.query("SELECT SUM(physical_cash) as total FROM shop_visits WHERE DATE(sale_date) = ?", [today])
        ]);

        // ==========================================
        // 4. FORMAT THE DATA
        // ==========================================
        const totalAssignments = totalAssignmentsRes[0].count;
        const completedAssignments = completedAssignmentsRes[0].count;
        const totalStoresToday = totalStoresTodayRes[0].count;
        const completedStoresToday = completedStoresTodayRes[0].count;

        const stats = {
            total_agents: totalAgentsRes[0].count,
            total_stores: totalStoresRes[0].count,
            total_regions: totalRegionsRes[0].count,
            completed_assignments: completedAssignments,
            total_assignments: totalAssignments,
            total_submissions: totalSubmissionsRes[0].count,
            completion_percentage: totalAssignments > 0 ? ((completedAssignments / totalAssignments) * 100).toFixed(2) : 0,
            
            agents_assigned: agentsAssignedRes[0].count,
            total_stores_today: totalStoresToday,
            completed_stores: completedStoresToday,
            total_malls: totalMallsRes[0].count,
            completed_malls: completedMallsRes[0].count,
            total_submissions_today: totalSubmissionsTodayRes[0].count,
            total_collected: totalCollectedRes[0].total || 0,
            completed_orders: completedStoresToday, // Assuming this maps to completed stores
            completion_rate_today: totalStoresToday > 0 ? ((completedStoresToday / totalStoresToday) * 100).toFixed(2) : 0
        };

        // ==========================================
        // 5. SAVE TO REDIS & RESPOND
        // ==========================================
        if (redisClient.isReady) {
            // Store the JSON string in Redis for 300 seconds (5 minutes)
            await redisClient.setEx(cacheKey, 300, JSON.stringify(stats));
        }

        res.status(200).json({
            status: 'success',
            message: 'Data fetched from MySQL and cached for 5 minutes',
            source: 'mysql',
            data: stats
        });

    } catch (error) {
        console.error('Dashboard Stats Error:', error);
        res.status(500).json({ status: 'error', message: 'Failed to generate dashboard statistics' });
    }
});

module.exports = router;