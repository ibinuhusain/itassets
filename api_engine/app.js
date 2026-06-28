require('dotenv').config();
const express = require('express');
const cors = require('cors');
const mysql = require('mysql2/promise');
const redis = require('redis');

const app = express();

// Middleware to parse incoming JSON data from the mobile app
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Allow your web dashboard and mobile app to talk to the API
app.use(cors());

// ==========================================
// 1. SETUP MYSQL CONNECTION POOL
// ==========================================
const dbPool = mysql.createPool({
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASS,
    database: process.env.DB_NAME,
    waitForConnections: true,
    connectionLimit: 50,
    queueLimit: 0,
    timezone: '+03:00'
});

// ==========================================
// 2. SETUP REDIS CLIENT
// ==========================================
const redisClient = redis.createClient({
    password: process.env.REDIS_PASS,
    socket: {
        host: process.env.REDIS_HOST,
        port: process.env.REDIS_PORT
    }
});

redisClient.on('error', (err) => console.error('Redis Client Error', err));
redisClient.on('connect', () => console.log('Successfully connected to Redis'));

// Export the pool so other files can use it later
module.exports = { dbPool, redisClient };

// ==========================================
// 3. BASIC HEALTH CHECK ROUTE
// ==========================================
app.get('/api/health', async (req, res) => {
    try {
        const [rows] = await dbPool.query('SELECT 1 + 1 AS solution');
        
        res.status(200).json({
            status: 'success',
            message: 'API is running fast and snappy!',
            db_status: 'connected',
            redis_status: redisClient.isReady ? 'connected' : 'disconnected',
            timestamp: new Date().toISOString()
        });
    } catch (error) {
        res.status(500).json({
            status: 'error',
            message: 'System connectivity issue',
            error: error.message
        });
    }
});

const authRoutes = require('./routes/v1/authRoutes');
const agentRoutes = require('./routes/v1/agentRoutes');
const adminRoutes = require('./routes/v1/adminRoutes');
const merchantRoutes = require('./routes/v1/merchant-api');

app.use('/api/v1/auth', authRoutes);
app.use('/api/v1/agent', agentRoutes);
app.use('/api/v1/admin', adminRoutes);
app.use('/api/v1/admin', merchantRoutes);

// ==========================================
// 4. START THE ENGINE
// ==========================================
const PORT = process.env.PORT || 3000;

// Connect to Redis in the background
redisClient.connect().catch((err) => {
    console.error('Redis failed to connect on boot, but server is still running:', err.message);
});

// Start the Express server
app.listen(PORT, () => {
    console.log(`🚀 API Engine is alive and listening!`);
});