const express = require('express');
const multer = require('multer');
const xlsx = require('xlsx');
const mysql = require('mysql2/promise');
const os = require('os'); // <-- Add this built-in module
const router = express.Router();

// Bulletproof fix: Use the server's default temporary RAM/temp drive
// This completely avoids cPanel folder permission crashes!
const upload = multer({ dest: os.tmpdir() });

// Middleware to parse JSON bodies
router.use(express.json());

// Create MySQL Pool using your existing .env variables
const pool = mysql.createPool({
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASS,
    database: process.env.DB_NAME,
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
});


router.use(express.json());

// ... keep the rest of your route code exactly the same ...

// ==========================================
// ADMIN DASHBOARD ENDPOINTS
// ==========================================

// POST: Upload & Parse Item Master with Grace Period
router.post('/upload-master', upload.single('masterFile'), async (req, res) => {
    try {
        if (!req.file) return res.status(400).json({ error: 'No file uploaded' });

        const gracePeriodDays = parseInt(req.body.gracePeriod) || 30;
        
        // Calculate exact expiration datetime
        const expirationDate = new Date();
        expirationDate.setDate(expirationDate.getDate() + gracePeriodDays);
        const formattedExpiry = expirationDate.toISOString().slice(0, 19).replace('T', ' ');

        // Parse the Excel file
        const workbook = xlsx.readFile(req.file.path);
        const sheetName = workbook.SheetNames[0];
        const rawData = xlsx.utils.sheet_to_json(workbook.Sheets[sheetName]);

        if (rawData.length === 0) {
            return res.status(400).json({ error: 'Excel file is empty' });
        }

        // Prepare data for bulk MySQL insertion
        // Mapping Excel column names to our database columns
        const values = rawData.map(item => [
            item.Barcode || item.barcode || '',
            item['Item Description'] || item.item_description || '',
            item['Location Code'] || item.location_code || '',
            item['Now Price'] || item.now_price || 0,
            item['Now Price Arabic'] || item.now_price_arabic || '',
            formattedExpiry
        ]);

        // Bulk insert into MySQL
        const query = `INSERT INTO item_master 
            (barcode, item_description, location_code, price, price_arabic, expires_at) 
            VALUES ?`;
        
        const [result] = await pool.query(query, [values]);

        res.json({ 
            success: true, 
            message: 'File processed successfully',
            total_items: result.affectedRows,
            expires_on: formattedExpiry,
            preview: rawData.slice(0, 5)
        });
    } catch (error) {
        console.error('Upload Error:', error);
        res.status(500).json({ error: 'Failed to process file or insert into database' });
    }
});

// POST: Create Brand
router.post('/api/admin/brands', async (req, res) => {
    const { name } = req.body;
    try {
        // Assuming you have a 'brands' table
        // await pool.execute('INSERT INTO brands (name) VALUES (?)', [name]);
        res.json({ success: true, message: `Brand ${name} created` });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

// POST: Create User/Merchant
router.post('/api/admin/users', async (req, res) => {
    const { username, password, role, locationCode } = req.body;
    try {
        // Assuming you have a 'users' table. Remember to hash passwords in production!
        // await pool.execute('INSERT INTO users (username, password, role, location_code) VALUES (?, ?, ?, ?)', 
        // [username, password, role, locationCode || null]);
        res.json({ success: true, message: 'User provisioned' });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

// ==========================================
// MOBILE APP (MERCHANT) ENDPOINTS
// ==========================================

// GET: Sync Data for Mobile App 
router.get('/api/merchant/sync/:location_code', async (req, res) => {
    const locationCode = req.params.location_code;
    
    try {
        // Fetch only the items matching the agent's location code
        const [rows] = await pool.execute(
            'SELECT barcode, item_description AS description, price, price_arabic FROM item_master WHERE location_code = ?',
            [locationCode]
        );

        res.json({
            success: true,
            location: locationCode,
            total_items: rows.length,
            items: rows
        });
    } catch (error) {
        console.error('Upload Error:', error);
        // Expose the exact error message for debugging
        res.status(500).json({ success: false, error: error.message, sqlMessage: error.sqlMessage });
    }
});

module.exports = router;