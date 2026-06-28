// /api_engine/routes/v1/authRoutes.js
const express = require('express');
const router = express.Router();
const jwt = require('jsonwebtoken');
const bcrypt = require('bcryptjs'); 
const { dbPool, redisClient } = require('../../app');

router.post('/login', async (req, res) => {
    try {
        const { username, password } = req.body;

        if (!username || !password) {
            return res.status(400).json({ status: 'error', message: 'Please provide username and password' });
        }

        // 1. Find the user in the database (adjust column names if needed)
        const [users] = await dbPool.query('SELECT * FROM users WHERE username = ? LIMIT 1', [username]);
        
        if (users.length === 0) {
            return res.status(401).json({ status: 'error', message: 'Invalid credentials' });
        }

        const user = users[0];

        // 2. Check the password
        // Note: If your old PHP app used plain text passwords (not recommended!), use: if (password !== user.password)
        const isMatch = await bcrypt.compare(password, user.password);
        if (!isMatch) {
            return res.status(401).json({ status: 'error', message: 'Invalid credentials' });
        }

        // 3. Generate the JWT Token (Valid for 24 hours)
        const token = jwt.sign(
            { id: user.id, role: user.role, name: user.name }, 
            process.env.JWT_SECRET, 
            { expiresIn: '24h' }
        );

        // 4. Send the token back to the Cordova app
        res.status(200).json({
            status: 'success',
            message: 'Login successful',
            token: token,
            user: {
                id: user.id,
                name: user.name,
                role: user.role
            }
        });

    } catch (error) {
        console.error('Login Error:', error);
        res.status(500).json({ status: 'error', message: 'Internal server error' });
    }
});

module.exports = router;