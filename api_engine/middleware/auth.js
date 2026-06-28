// /api_engine/middleware/auth.js
const jwt = require('jsonwebtoken');

const verifyToken = (req, res, next) => {
    // Look for the token in the headers
    const bearerHeader = req.headers['authorization'];

    if (!bearerHeader) {
        return res.status(403).json({ status: 'error', message: 'Access Denied: No token provided' });
    }

    // The header usually looks like: "Bearer eyJhbGciOiJIUzI1..."
    const token = bearerHeader.split(' ')[1];

    try {
        // Verify the token using your secret key from the .env file
        const decoded = jwt.verify(token, process.env.JWT_SECRET);
        
        // Attach the user's ID and Role to the request so we can use it in our routes
        req.user = decoded; 
        next(); // Let them pass
    } catch (error) {
        return res.status(401).json({ status: 'error', message: 'Invalid or Expired Token' });
    }
};

// Optional: A stricter bouncer just for Admin routes
const verifyAdmin = (req, res, next) => {
    verifyToken(req, res, () => {
        if (req.user.role !== 'admin') {
            return res.status(403).json({ status: 'error', message: 'Access Denied: Admins Only' });
        }
        next();
    });
};

module.exports = { verifyToken, verifyAdmin };