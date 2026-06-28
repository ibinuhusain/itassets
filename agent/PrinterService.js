// printer-service.js
class PrinterService {
    constructor() {
        this.isConnected = false;
        this.printerType = 'bluetooth'; // or 'wifi'
    }

    // Discover available printers
    async discoverPrinters() {
        if (window.thermalPrinter) {
            return new Promise((resolve, reject) => {
                window.thermalPrinter.discoverPrinters(
                    (result) => resolve(result),
                    (error) => reject(error)
                );
            });
        } else {
            throw new Error('Thermal printer service not available');
        }
    }

    // Connect to printer
    async connect(options) {
        if (options.type === 'bluetooth') {
            this.printerType = 'bluetooth';
            return await this.connectBluetooth(options.deviceId);
        } else if (options.type === 'wifi') {
            this.printerType = 'wifi';
            return await this.connectWiFi(options.ip, options.port);
        }
    }

    // Bluetooth printer connection
    async connectBluetooth(deviceId) {
        if (window.thermalPrinter) {
            return new Promise((resolve, reject) => {
                window.thermalPrinter.connectBluetooth(deviceId,
                    (success) => {
                        this.isConnected = true;
                        resolve(success);
                    },
                    (error) => reject(error)
                );
            });
        } else {
            throw new Error('Thermal printer service not available');
        }
    }

    // WiFi printer connection
    async connectWiFi(ip, port) {
        if (window.thermalPrinter) {
            return new Promise((resolve, reject) => {
                window.thermalPrinter.connectWiFi(ip, port,
                    (success) => {
                        this.isConnected = true;
                        resolve(success);
                    },
                    (error) => reject(error)
                );
            });
        } else {
            throw new Error('Thermal printer service not available');
        }
    }

    // Print receipt
    async printReceipt(assignmentData) {
        if (!this.isConnected) {
            throw new Error('Printer not connected');
        }

        if (window.thermalPrinter) {
            return new Promise((resolve, reject) => {
                window.thermalPrinter.printReceipt(assignmentData,
                    (success) => resolve(success),
                    (error) => reject(error)
                );
            });
        } else {
            throw new Error('Thermal printer service not available');
        }
    }

    // Print raw data
    async printRaw(data) {
        if (!this.isConnected) {
            throw new Error('Printer not connected');
        }

        if (window.thermalPrinter) {
            return new Promise((resolve, reject) => {
                window.thermalPrinter.printRaw(data,
                    (success) => resolve(success),
                    (error) => reject(error)
                );
            });
        } else {
            throw new Error('Thermal printer service not available');
        }
    }

    // Disconnect printer
    async disconnect() {
        if (window.thermalPrinter) {
            return new Promise((resolve, reject) => {
                window.thermalPrinter.disconnect(
                    (success) => {
                        this.isConnected = false;
                        resolve(success);
                    },
                    (error) => reject(error)
                );
            });
        } else {
            throw new Error('Thermal printer service not available');
        }
    }
}

// Global instance
window.printerService = new PrinterService();