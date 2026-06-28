// Thermal Printer Service Bridge
// This service provides access to native Android Bluetooth functions for thermal printer connectivity

document.addEventListener('deviceready', function() {
    console.log('Device ready - Initializing thermal printer service');
    
    // Add this check
    if (window.thermalPrinter) {
        console.log('Thermal Printer plugin is available');
    } else {
        console.error('Thermal Printer plugin NOT available');
    }
    
    // Check if thermal printer plugin is available
    if (typeof thermalPrinter !== 'undefined') {
        console.log('Thermal printer plugin is available');
        
        // Attach the thermal printer service to the global window object
        window.thermalPrinter = window.thermalPrinter || thermalPrinter;
        
        // Initialize printer service instance
        if (window.printerService) {
            console.log('Printer service already initialized');
        }
    } else {
        console.warn('Thermal printer plugin not available. Make sure it\'s properly installed.');
        
        // Create a mock implementation for testing purposes
        window.thermalPrinter = {
            discoverPrinters: function(successCallback, errorCallback) {
                console.warn('Mock: Discovering printers...');
                setTimeout(() => {
                    errorCallback('Thermal printer plugin not installed');
                }, 100);
            },
            connectBluetooth: function(deviceId, successCallback, errorCallback) {
                setTimeout(() => {
                    errorCallback('Thermal printer plugin not installed');
                }, 100);
            },
            connectWiFi: function(ipAddress, port, successCallback, errorCallback) {
                setTimeout(() => {
                    errorCallback('Thermal printer plugin not installed');
                }, 100);
            },
            printRaw: function(data, successCallback, errorCallback) {
                setTimeout(() => {
                    errorCallback('Thermal printer plugin not installed');
                }, 100);
            },
            printReceipt: function(receiptData, successCallback, errorCallback) {
                setTimeout(() => {
                    errorCallback('Thermal printer plugin not installed');
                }, 100);
            },
            disconnect: function(successCallback, errorCallback) {
                setTimeout(() => {
                    errorCallback('Thermal printer plugin not installed');
                }, 100);
            }
        };
    }
}, false);

// Additional event listener to handle cases where deviceready fires before this script loads
if (window.device && window.device.platform) {
    // Device is already ready
    if (typeof thermalPrinter !== 'undefined') {
        window.thermalPrinter = window.thermalPrinter || thermalPrinter;
    }
}

// Utility function to check if printer service is available
window.isPrinterServiceAvailable = function() {
    return typeof thermalPrinter !== 'undefined' && thermalPrinter !== null;
};


// printer-bridge.js
document.addEventListener('deviceready', function() {
    console.log('Device ready - Checking for thermal printer plugin');
    
    if (window.thermalPrinter) {
        console.log('Thermal Printer plugin is available');
        // Test basic functionality
        window.thermalPrinter.discoverPrinters(
            function(printers) {
                console.log('Discovered printers:', printers);
            },
            function(error) {
                console.error('Error discovering printers:', error);
            }
        );
    } else {
        console.error('Thermal Printer plugin NOT available');
    }
}, false);