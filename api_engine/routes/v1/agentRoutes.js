// /api_engine/routes/v1/agentRoutes.js
const express = require('express');
const router = express.Router();
const { verifyToken } = require('../../middleware/auth');
const { dbPool, redisClient } = require('../../app');

// 1. Fetch Today's Assigned Stores for the Logged-in Agent
// NOTE: If your APK literally calls "get_stores.php", change the string below from '/stores' to '/get_stores.php'
router.get('/stores', verifyToken, async (req, res) => {
    try {
        // req.user.id comes from the verifyToken middleware! 
        const agentId = req.user.id; 
        
        // Get today's date in YYYY-MM-DD format based on Saudi time
        const today = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Riyadh' });

        const query = `
            SELECT 
                da.id AS assignment_id,
                da.status AS assignment_status,
                s.id AS store_id,
                s.name AS store_name,
                s.mall,
                s.city,
                s.brand
            FROM daily_assignments da
            JOIN stores s ON da.store_id = s.id
            WHERE da.agent_id = ? AND DATE(da.date_assigned) = ?
            ORDER BY da.status ASC, s.name ASC
        `;

        const [stores] = await dbPool.query(query, [agentId, today]);

        // Return the data to the mobile app
        res.status(200).json({
            status: 'success',
            message: `Found ${stores.length} stores assigned for today.`,
            data: stores
        });

    } catch (error) {
        console.error('Error fetching agent stores:', error);
        res.status(500).json({ status: 'error', message: 'Failed to load stores' });
    }
});

// 2. Submit a Shop Visit (Cash Collection + Proof Image)
// NOTE: If your APK calls "submit_visit.php", change this to '/submit_visit.php'
router.post('/visits/submit', verifyToken, async (req, res) => {
    try {
        const agentId = req.user.id;
        const { shop_id, z_report, physical_cash, discrepancy, reason, proof_image } = req.body;
        
        const todayDate = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Riyadh' });

        // Insert the visit record
        const insertQuery = `
            INSERT INTO shop_visits 
            (shop_id, agent_id, proof_image, z_report, physical_cash, discrepancy, reason, sale_date, collection_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        `;
        
        await dbPool.query(insertQuery, [
            shop_id, agentId, proof_image, z_report, physical_cash, discrepancy, reason, todayDate, todayDate
        ]);

        // Update the assignment status to 'completed'
        await dbPool.query(
            "UPDATE daily_assignments SET status = 'completed' WHERE agent_id = ? AND store_id = ? AND DATE(date_assigned) = ?", 
            [agentId, shop_id, todayDate]
        );

        res.status(200).json({
            status: 'success',
            message: 'Shop visit submitted successfully!'
        });

    } catch (error) {
        console.error('Error submitting visit:', error);
        res.status(500).json({ status: 'error', message: 'Failed to submit visit' });
    }
});

module.exports = router;