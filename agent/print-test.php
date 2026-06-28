<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Collection Receipt - Thermal Printer Test</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
    .container { max-width: 600px; margin: 20px auto; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .form-group { margin-bottom: 20px; }
    label { display: block; margin-bottom: 8px; font-weight: bold; color: #333; }
    input, select, textarea { 
        width: 100%; 
        padding: 12px; 
        border: 1px solid #ddd; 
        border-radius: 4px; 
        font-size: 14px; 
        box-sizing: border-box;
        transition: border-color 0.3s;
    }
    input:focus, select:focus, textarea:focus {
        outline: none;
        border-color: #4CAF50;
        box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.2);
    }
    button { 
        padding: 12px 20px; 
        border: none; 
        border-radius: 4px; 
        cursor: pointer; 
        margin-right: 10px; 
        margin-bottom: 10px;
        font-weight: 600;
        transition: all 0.3s;
        min-width: 150px;
    }
    button:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    #connectBtn { background-color: #4CAF50; color: white; }
    #connectBtn:hover:not(:disabled) { background-color: #45a049; }
    #printBtn { background-color: #2196F3; color: white; }
    #printBtn:hover:not(:disabled) { background-color: #0b7dda; }
    #printCustomBtn { background-color: #ff9800; color: white; }
    #printCustomBtn:hover:not(:disabled) { background-color: #e68a00; }
    #disconnectBtn { background-color: #f44336; color: white; }
    #disconnectBtn:hover:not(:disabled) { background-color: #d32f2f; }
    #discoverBtn { background-color: #9c27b0; color: white; }
    #discoverBtn:hover:not(:disabled) { background-color: #7b1fa2; }
    .status { padding: 12px; margin: 15px 0; border-radius: 4px; font-size: 14px; }
    .success { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
    .error { background-color: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
    .info { background-color: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb; }
    .printer-list { 
        margin-top: 15px; 
        padding: 15px; 
        background-color: #f9f9f9; 
        border-radius: 4px;
        border: 1px solid #eee;
        max-height: 200px;
        overflow-y: auto;
    }
    .printer-item { 
        padding: 10px; 
        cursor: pointer; 
        border-bottom: 1px solid #eee;
        transition: background-color 0.2s;
    }
    .printer-item:last-child { border-bottom: none; }
    .printer-item:hover { background-color: #f0f0f0; }
    .loading {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(255,255,255,.3);
        border-radius: 50%;
        border-top-color: #fff;
        animation: spin 1s ease-in-out infinite;
        margin-right: 10px;
        vertical-align: middle;
    }
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    .debug-toggle {
        color: #2196F3;
        cursor: pointer;
        font-size: 14px;
        margin-top: 10px;
        display: inline-block;
    }
    .hidden { display: none; }
  </style>
</head>

<body>
<div class="container">
  <h2>Thermal Printer Test Page</h2>

  <div id="printerStatus" class="status info">
    <span id="statusText">Printer Status: Initializing...</span>
    <span id="statusLoading" class="loading hidden"></span>
  </div>

  <div class="form-group">
    <label for="printerType">Printer Type:</label>
    <select id="printerType">
      <option value="bluetooth">Bluetooth</option>
      <option value="wifi">WiFi</option>
    </select>
  </div>

  <div id="bluetoothSection">
    <div class="form-group">
      <label for="bluetoothDevice">Bluetooth Device ID/MAC:</label>
      <input type="text" id="bluetoothDevice" placeholder="e.g., 00:11:22:33:AA:BB" value="">
    </div>
    <button id="discoverBtn" class="btn-discover">
      <span id="discoverText">Discover Bluetooth Printers</span>
      <span id="discoverLoading" class="loading hidden"></span>
    </button>
    <div id="printerList" class="printer-list hidden"></div>
  </div>

  <div id="wifiSection" class="hidden">
    <div class="form-group">
      <label for="printerIp">Printer IP Address:</label>
      <input type="text" id="printerIp" placeholder="e.g., 192.168.1.100" value="192.168.1.100">
    </div>

    <div class="form-group">
      <label for="printerPort">Printer Port:</label>
      <input type="text" id="printerPort" placeholder="e.g., 9100" value="9100">
    </div>
  </div>

  <div class="form-group">
    <label for="testContent">Test Content:</label>
    <textarea id="testContent" rows="4">Test thermal print from Apparels Collection system. This is a test of the thermal printing functionality. If you can read this, the printer is working correctly!</textarea>
  </div>

  <div class="button-group">
    <button id="connectBtn" disabled>
      <span id="connectText">Connect to Printer</span>
      <span id="connectLoading" class="loading hidden"></span>
    </button>
    <button id="printBtn" disabled>
      <span id="printText">Print Test Receipt</span>
      <span id="printLoading" class="loading hidden"></span>
    </button>
    <button id="printCustomBtn" disabled>
      <span id="printCustomText">Print Custom Text</span>
      <span id="printCustomLoading" class="loading hidden"></span>
    </button>
    <button id="disconnectBtn" class="btn-secondary" disabled>Disconnect</button>
  </div>

  <h3>Test Data for Receipt</h3>
  <div class="form-group">
    <label for="storeName">Store Name:</label>
    <input type="text" id="storeName" value="TEST STORE NAME">
  </div>

  <div class="form-group">
    <label for="agentName">Agent Name:</label>
    <input type="text" id="agentName" value="TEST AGENT">
  </div>

  <div class="form-group">
    <label for="amountCollected">Amount Collected:</label>
    <input type="text" id="amountCollected" value="Rs. 5,000.00">
  </div>

  <div class="form-group">
    <label for="pendingAmount">Pending Amount:</label>
    <input type="text" id="pendingAmount" value="Rs. 2,500.00">
  </div>

  <div class="form-group">
    <label for="targetAmount">Target Amount:</label>
    <input type="text" id="targetAmount" value="Rs. 10,000.00">
  </div>

  <div class="debug-toggle" onclick="toggleDebug()">Toggle Debug Information</div>
  <div id="debugInfo" class="hidden">
    <h4>Debug Information:</h4>
    <pre id="debugOutput"></pre>
  </div>
</div>

<script>
// Wait for the printer bridge to load
document.addEventListener('printerservice:ready', initializeApp);
document.addEventListener('DOMContentLoaded', initializeApp);

// Global state
let printerService = null;
let isConnected = false;

async function initializeApp() {
    try {
        // Check if printer service is available
        if (!window.printerService) {
            updateStatus("Printer service not available. Please run in the mobile app.", true);
            return;
        }

        printerService = window.printerService;
        updateStatus("Printer service initialized. Ready to connect.");

        // Enable UI elements
        enableUI(true);

        // Set up event listeners
        setupEventListeners();

        // Check initial connection status
        checkConnectionStatus();

    } catch (error) {
        console.error("Error initializing app:", error);
        updateStatus(`Initialization failed: ${error.message}`, true);
    }
}

function setupEventListeners() {
    // Printer type selection
    document.getElementById("printerType").addEventListener("change", function() {
        const isBluetooth = this.value === 'bluetooth';
        document.getElementById("bluetoothSection").classList.toggle("hidden", !isBluetooth);
        document.getElementById("wifiSection").classList.toggle("hidden", isBluetooth);
    });

    // Discover button
    document.getElementById("discoverBtn").addEventListener("click", discoverPrinters);

    // Connect button
    document.getElementById("connectBtn").addEventListener("click", connectToPrinter);

    // Print buttons
    document.getElementById("printBtn").addEventListener("click", printTestReceipt);
    document.getElementById("printCustomBtn").addEventListener("click", printCustomText);

    // Disconnect button
    document.getElementById("disconnectBtn").addEventListener("click", disconnectPrinter);
}

async function checkConnectionStatus() {
    try {
        const connected = await printerService.checkPrinterAvailability();
        if (connected) {
            updateStatus("Already connected to printer", false);
            setConnectedState(true);
        }
    } catch (error) {
        console.error("Error checking connection status:", error);
    }
}

async function discoverPrinters() {
    setLoading('discover', true);
    try {
        const printers = await printerService.discoverPrinters();
        displayPrinters(printers);
    } catch (error) {
        console.error("Error discovering printers:", error);
        updateStatus(`Discovery failed: ${error.message}`, true);
    } finally {
        setLoading('discover', false);
    }
}

function displayPrinters(printers) {
    const printerList = document.getElementById("printerList");
    
    if (!printers || printers.length === 0) {
        printerList.innerHTML = '<p>No printers found. Make sure your printer is paired and turned on.</p>';
        printerList.classList.remove("hidden");
        updateStatus("No printers found", true);
        return;
    }

    let html = '<h4>Available Printers:</h4>';
    printers.forEach(printer => {
        html += `
            <div class="printer-item" data-id="${printer.id}">
                <strong>${printer.name}</strong><br>
                <small>${printer.id} - ${printer.type}</small>
            </div>
        `;
    });

    printerList.innerHTML = html;
    printerList.classList.remove("hidden");

    // Add click handlers to printer items
    document.querySelectorAll('.printer-item').forEach(item => {
        item.addEventListener('click', () => {
            const deviceId = item.getAttribute('data-id');
            document.getElementById('bluetoothDevice').value = deviceId;
            updateStatus(`Selected printer: ${item.textContent.trim()}`);
        });
    });

    updateStatus(`Found ${printers.length} printer(s)`);
}

async function connectToPrinter() {
    const type = document.getElementById("printerType").value;
    setLoading('connect', true);

    try {
        if (type === 'bluetooth') {
            const deviceId = document.getElementById("bluetoothDevice").value.trim();
            if (!deviceId) {
                throw new Error("Please enter a Bluetooth device ID");
            }
            
            // Make sure printerService is available
            if (!window.printerService || typeof window.printerService.connectBluetooth !== 'function') {
                throw new Error("Printer service not properly initialized");
            }

            // Call the connectBluetooth method
            await window.printerService.connectBluetooth(deviceId);
            updateStatus(`Successfully connected to ${type} printer`);
            setConnectedState(true);
        }

        if (connection) {
            updateStatus(`Successfully connected to ${type} printer`);
            setConnectedState(true);
        } else {
            throw new Error("Connection failed");
        }
    } catch (error) {
        console.error("Connection error:", error);
        updateStatus(`Connection failed: ${error.message}`, true);
        setConnectedState(false);
    } finally {
        setLoading('connect', false);
    }
}

async function printTestReceipt() {
    setLoading('print', true);
    
    try {
        const receiptData = {
            store_name: document.getElementById("storeName").value || "Default Store",
            agent_name: document.getElementById("agentName").value || "Default Agent",
            date: new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }),
            amount_collected: document.getElementById("amountCollected").value || "Rs. 0.00",
            pending_amount: document.getElementById("pendingAmount").value || "Rs. 0.00",
            target_amount: document.getElementById("targetAmount").value || "Rs. 0.00"
        };

        logDebug("Printing test receipt...");
        await printerService.printReceipt(receiptData);
        updateStatus("Test receipt printed successfully!");
    } catch (error) {
        console.error("Print error:", error);
        updateStatus(`Print failed: ${error.message}`, true);
    } finally {
        setLoading('print', false);
    }
}

async function printCustomText() {
    setLoading('printCustom', true);
    
    try {
        const text = document.getElementById("testContent").value;
        if (!text.trim()) {
            throw new Error("Please enter some text to print");
        }

        logDebug("Printing custom text...");
        await printerService.printRaw(text + '\n\n');
        updateStatus("Custom text printed successfully!");
    } catch (error) {
        console.error("Print error:", error);
        updateStatus(`Print failed: ${error.message}`, true);
    } finally {
        setLoading('printCustom', false);
    }
}

async function disconnectPrinter() {
    try {
        await printerService.disconnect();
        updateStatus("Disconnected from printer");
        setConnectedState(false);
    } catch (error) {
        console.error("Disconnect error:", error);
        updateStatus(`Disconnect failed: ${error.message}`, true);
    }
}

function setConnectedState(connected) {
    isConnected = connected;
    
    // Enable/disable buttons based on connection state
    document.getElementById("connectBtn").disabled = connected;
    document.getElementById("disconnectBtn").disabled = !connected;
    document.getElementById("printBtn").disabled = !connected;
    document.getElementById("printCustomBtn").disabled = !connected;
    document.getElementById("discoverBtn").disabled = connected;
    
    // Update status
    if (connected) {
        document.getElementById("printerStatus").classList.remove("error");
        document.getElementById("printerStatus").classList.add("success");
    }
}

function enableUI(enabled) {
    document.getElementById("connectBtn").disabled = !enabled;
    document.getElementById("discoverBtn").disabled = !enabled;
}

function setLoading(button, isLoading) {
    const elements = {
        discover: { text: 'discoverText', loading: 'discoverLoading', btn: 'discoverBtn' },
        connect: { text: 'connectText', loading: 'connectLoading', btn: 'connectBtn' },
        print: { text: 'printText', loading: 'printLoading', btn: 'printBtn' },
        printCustom: { text: 'printCustomText', loading: 'printCustomLoading', btn: 'printCustomBtn' }
    };

    const element = elements[button];
    if (!element) return;

    const textEl = document.getElementById(element.text);
    const loadingEl = document.getElementById(element.loading);
    const btnEl = document.getElementById(element.btn);

    if (isLoading) {
        textEl.style.display = 'none';
        loadingEl.classList.remove('hidden');
        btnEl.disabled = true;
    } else {
        textEl.style.display = 'inline';
        loadingEl.classList.add('hidden');
        btnEl.disabled = false;
    }
}

function updateStatus(message, isError = false) {
    const statusText = document.getElementById("statusText");
    statusText.textContent = message;
    
    const statusEl = document.getElementById("printerStatus");
    statusEl.className = 'status ' + (isError ? 'error' : 'success');
    
    logDebug(message);
}

function logDebug(message) {
    const debugOutput = document.getElementById("debugOutput");
    const timestamp = new Date().toISOString().split('T')[1].split('.')[0];
    debugOutput.textContent = `[${timestamp}] ${message}\n${debugOutput.textContent}`;
}

function toggleDebug() {
    const debugInfo = document.getElementById("debugInfo");
    debugInfo.classList.toggle("hidden");
}

// Fallback for testing without Cordova
if (!window.cordova) {
    window.thermalPrinter = {
        discoverPrinters: (success) => success([{ id: 'mock:printer1', name: 'Mock Printer', type: 'bluetooth' }]),
        connectBluetooth: (id, success) => setTimeout(() => success("Connected to mock printer"), 1000),
        printRaw: (data, success) => setTimeout(() => success("Printed mock data"), 500),
        printReceipt: (data, success) => setTimeout(() => success("Printed mock receipt"), 500),
        disconnect: (success) => setTimeout(() => success("Disconnected"), 300),
        isConnected: (success) => setTimeout(() => success(false), 100)
    };
    
    // Create a mock printer service
    window.printerService = {
        discoverPrinters: () => Promise.resolve([{ id: 'mock:printer1', name: 'Mock Printer', type: 'bluetooth' }]),
        connect: (options) => Promise.resolve(true),
        printReceipt: (data) => Promise.resolve(true),
        printRaw: (data) => Promise.resolve(true),
        disconnect: () => Promise.resolve(true),
        checkPrinterAvailability: () => Promise.resolve(false)
    };
    
    // Dispatch the ready event
    setTimeout(() => {
        const event = new CustomEvent('printerservice:ready');
        document.dispatchEvent(event);
    }, 500);
}
</script>

</body>
</html>