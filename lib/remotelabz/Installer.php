<?php
/**
 * RemoteLabz Application Installer (Optimized)
 * 
 * This class handles RemoteLabz-specific installation tasks.
 * System requirements (Apache, PHP, MySQL, RabbitMQ, etc.) should be 
 * installed separately using the bash installation script.
 * 
 * Changes from original:
 * - Removed system package checks (done in bash script)
 * - Simplified requirements check (only PHP version, Apache, Composer)
 * - Removed configureEnvironment (now handled by bash script via .env.local)
 * - Streamlined Apache configuration (basic setup done in bash)
 * - Added better error messages
 * - Added skip flags for flexibility
 * - Improved JWT token handling with user input
 */

namespace RemoteLabz;

use \Exception;
use RemoteLabz\Logger;
use RemoteLabz\System;
use RemoteLabz\Exception\AlreadyExistException;

class Installer
{
    private $logger;
    private $installPath;
    private $options;

    public function __construct(string $installPath = "", array $options = [], Logger $log = null)
    {
        $this->installPath = $installPath;
        $this->options = $options;
        $this->logger = $log;
        if ($log == null) {
            $this->logger = new Logger();
        }
    }

    public static function create(string $installPath = "", array $options, Logger $log = null)
    {
        return new static($installPath, $options, $log);
    }

    /**
     * Check if this script is executed as root.
     */
    function checkRoot()
    {
        $username = posix_getpwuid(posix_geteuid())['name'];
        if ($username != "root") {
            throw new Exception("Installation aborted, root is required! Please launch this script as root or with sudo.");
        }
    }

    /**
     * Check that required tools are available.
     * System packages should already be installed by bash script.
     */
    function checkRequirements()
    {
        // Check PHP version (should be 8.4+ for optimal performance)
        if (!(strnatcmp(phpversion(), '8.0.0') >= 0)) {
            throw new Exception("You need PHP 8.0 or higher to use RemoteLabz. Current version: " . phpversion());
        }
        
        // Check Apache
        if (!System::commandExists("apache2ctl")) {
            throw new Exception("Apache 2 has not been found on your system. Please run the bash installation script first.");
        }
        
        // Check Composer
        if (!System::commandExists("composer")) {
            throw new Exception("Composer has not been found on your system. Please run the bash installation script first.");
        }
        
        // Check Node/Yarn
        if (!System::commandExists("node")) {
            $this->logger->warning("Node.js not found. Yarn installation may fail.");
        }
        
        if (!System::commandExists("yarn")) {
            $this->logger->warning("Yarn not found. Please install it: npm install -g yarn");
        }
    }

    /**
     * Full workflow of RemoteLabz installation.
     */
    function install()
    {
        $this->logger->debug("Starting RemoteLabz application installation");
        echo "\n";
        Logger::print("========================================\n", Logger::COLOR_CYAN);
        Logger::print("  RemoteLabz Application Installer\n", Logger::COLOR_CYAN);
        Logger::print("========================================\n", Logger::COLOR_CYAN);
        echo "\n";

        // Step 1: Copy or symlink files
        if (array_key_exists('symlink', $this->options) && $this->options['symlink']) {
            echo "📁 Creating symlink to " . $this->installPath . "... ";
            try {
                $this->symlinkFiles();
                echo "OK ✔️\n";
            } catch (AlreadyExistException $e) {
                Logger::print("Warning: Symlink already exists. Skipping.\n", Logger::COLOR_YELLOW);
            }
        } else {
            echo "📁 Copying files to " . $this->installPath . "... ";
            try {
                $this->copyFiles();
                echo "OK ✔️\n";
            } catch (AlreadyExistException $e) {
                Logger::print("Warning: Directory already exists. Skipping file copy.\n", Logger::COLOR_YELLOW);
            }
        }

        // Change to install directory
        if (chdir($this->installPath) == false) {
            throw new Exception("Could not change to install directory: " . $this->installPath);
        }

        // Step 2: Install Composer packages
        echo "🎶 Installing Composer packages... ";
        if ($this->configureComposer()) {
            echo "OK ✔️\n";
        } else {
            throw new Exception("Failed to install Composer packages.");
        }

        // Step 3: Install Yarn packages
        echo "📦 Installing Yarn packages...\n";
        try {
            $this->configureYarn();
            echo "Yarn packages installed ✔️\n";
        } catch (Exception $e) {
            throw new Exception("Failed to install Yarn packages: " . $e->getMessage());
        }

        // Step 4: Configure git safe directory
        echo "🔧 Configuring git safe directory... ";
        try {
            exec("git config --system --add safe.directory /opt/remotelabz", $output, $returnCode);
            if ($returnCode) {
                throw new Exception("Could not configure git safe directory.");
            }
            echo "OK ✔️\n";
        } catch (Exception $e) {
            Logger::print("Warning: " . $e->getMessage() . "\n", Logger::COLOR_YELLOW);
        }

        // Step 5: Warm cache
        echo "🔥 Warming Symfony cache... ";
        if ($this->configureCache($this->options['environment'])) {
            echo "OK ✔️\n";
        } else {
            throw new Exception("Failed to warm application cache.");
        }

        // Step 6: Set file permissions
        echo "👮 Setting file permissions... ";
        if (!array_key_exists('no-permission', $this->options) || !$this->options['no-permission']) {
            // Create remotelabz user and group if they don't exist
            $returnCode = 0;
            $output = [];
            exec("getent passwd remotelabz > /dev/null", $output, $returnCode);
            if ($returnCode) {
                exec("useradd remotelabz");
            }
            exec("getent group remotelabz > /dev/null", $output, $returnCode);
            if ($returnCode) {
                exec("groupadd remotelabz");
            }
            
            try {
                $this->rchown($this->installPath, "remotelabz", "www-data");
                echo "OK ✔️\n";
            } catch (Exception $e) {
                throw new Exception("Error setting file permissions: " . $e->getMessage());
            }
        } else {
            Logger::print("Skipping...\n", Logger::COLOR_YELLOW);
        }

        // Step 7: Configure Apache
        echo "🌎 Configuring Apache... ";
        try {
            $this->configureApache(
                $this->options['port'], 
                $this->options['server-name'], 
                $this->options['max-filesize']
            );
            echo "OK ✔️\n";
        } catch (Exception $e) {
            throw new Exception("Error configuring Apache: " . $e->getMessage());
        }

        // Step 8: Create and enable RemoteLabz services
        echo "🔨 Creating RemoteLabz services... ";
        try {
            $this->configureMessengerService();
            $this->configureProxyService();
            $this->configureRouteMonitorService();
            $this->configureRouteMonitorTimerService();
            $this->configureCleanNotificationService();
            $this->configureCleanNotificationTimerService();
            $this->configureGitVersionService();
            $this->configureGitVersionTimerService();
            echo "OK ✔️\n";
        } catch (Exception $e) {
            throw new Exception("Error creating services: " . $e->getMessage());
        }

        // Step 9: Enable and start services
        echo "▶️  Enabling RemoteLabz services... ";
        try {
            exec("systemctl daemon-reload");
            exec("systemctl enable remotelabz.service");
            exec("systemctl enable remotelabz-proxy.service");
            exec("systemctl enable remotelabz-route-monitor.timer");
            exec("systemctl enable remotelabz-clean-notification.timer");
            exec("systemctl enable remotelabz-git-version-update.timer");
            exec("systemctl start remotelabz.service");
            exec("systemctl start remotelabz-proxy.service");
            exec("systemctl start remotelabz-route-monitor.timer");
            exec("systemctl start remotelabz-clean-notification.timer");
            exec("systemctl start remotelabz-git-version-update.timer");
            echo "OK ✔️\n";
        } catch (Exception $e) {
            throw new Exception("Error starting services: " . $e->getMessage());
        }

        // Step 10: Configure JWT
        echo "🔐 Configuring JWT...\n";
        try {
            $jwtPassphrase = $this->configureJWT();
            echo "JWT configured ✔️\n";
            echo "\n";
            Logger::print("🔥 IMPORTANT: Your JWT passphrase is: '$jwtPassphrase' 🔥\n", Logger::COLOR_YELLOW);
            Logger::print("Please save this passphrase securely!\n", Logger::COLOR_YELLOW);
            echo "\n";
        } catch (Exception $e) {
            throw new Exception("Error while configuring JWT: " . $e->getMessage());
        }

        // Step 11: Configure database
        echo "🗄️  Configuring database...\n";
        if ($this->configure_db()) {
            echo "Database configured ✔️\n";
        } else {
            throw new Exception("Failed to configure database.");
        }

        // Installation complete
        echo "\n";
        Logger::print("========================================\n", Logger::COLOR_GREEN);
        Logger::print("  ✅ Installation complete!\n", Logger::COLOR_GREEN);
        Logger::print("========================================\n", Logger::COLOR_GREEN);
        echo "\n";
    }

    /**
     * Configure JWT with automatically generated passphrase.
     * 
     * @return string The JWT passphrase used
     * @throws Exception
     */
    private function configureJWT(): string
    {
        // Create JWT directory
        @mkdir($this->installPath . '/config/jwt', 0755, true);
        
        // Generate secure random passphrase
        $jwtPassphrase = $this->generateSecurePassphrase(32);
        
        // Generate JWT keys using the passphrase
        if (!$this->genkey_jwt($jwtPassphrase)) {
            throw new Exception("Failed to generate JWT keys.");
        }
        
        // Write passphrase to .env.local
        $envLocalFile = $this->installPath . "/.env.local";
        $envContent = file_exists($envLocalFile) ? file_get_contents($envLocalFile) : '';
        
        // Remove existing JWT_PASSPHRASE if present
        $envContent = preg_replace('/^JWT_PASSPHRASE=.*$/m', '', $envContent);
        $envContent = trim($envContent);
        
        // Add new JWT_PASSPHRASE
        if (!empty($envContent)) {
            $envContent .= "\n";
        }
        $envContent .= "JWT_PASSPHRASE=\"" . addslashes($jwtPassphrase) . "\"\n";
        
        if (!file_put_contents($envLocalFile, $envContent)) {
            throw new Exception("Failed to write JWT passphrase to .env.local");
        }
        
        // Set correct permissions on JWT directory
        $this->rchown($this->installPath . "/config/jwt", "www-data", "www-data");
        
        return $jwtPassphrase;
    }

    /**
     * Generate JWT keys with provided passphrase.
     * 
     * @param string $passphrase The passphrase to use for key encryption
     * @return bool True on success, false on failure
     */
    private function genkey_jwt(string $passphrase): bool
    {
        $privateKeyPath = $this->installPath . '/config/jwt/private.pem';
        $publicKeyPath = $this->installPath . '/config/jwt/public.pem';
        
        // Create a temporary file with the passphrase for openssl
        $tempPassFile = tempnam(sys_get_temp_dir(), 'jwt_pass_');
        file_put_contents($tempPassFile, $passphrase);
        
        try {
            $returnCode = 0;
            $output = [];
            
            // Generate private key with passphrase from file
            $cmd = sprintf(
                "openssl genpkey -out %s -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass file:%s 2>&1",
                escapeshellarg($privateKeyPath),
                escapeshellarg($tempPassFile)
            );
            exec($cmd, $output, $returnCode);
            
            if ($returnCode !== 0) {
                $this->logger->error("Failed to generate private key: " . implode("\n", $output));
                return false;
            }
            
            // Generate public key
            $cmd = sprintf(
                "openssl pkey -in %s -out %s -pubout -passin file:%s 2>&1",
                escapeshellarg($privateKeyPath),
                escapeshellarg($publicKeyPath),
                escapeshellarg($tempPassFile)
            );
            exec($cmd, $output, $returnCode);
            
            if ($returnCode !== 0) {
                $this->logger->error("Failed to generate public key: " . implode("\n", $output));
                return false;
            }
            
            // Set permissions
            chmod($privateKeyPath, 0600);
            chmod($publicKeyPath, 0644);
            
            return true;
            
        } finally {
            // Always clean up temp file
            if (file_exists($tempPassFile)) {
                unlink($tempPassFile);
            }
        }
    }

    /**
     * Generate a secure random passphrase.
     * 
     * @param int $length Length of the passphrase
     * @return string The generated passphrase
     */
    private function generateSecurePassphrase(int $length = 32): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()-_=+[]{}|;:,.<>?';
        $charactersLength = strlen($characters);
        $passphrase = '';
        
        for ($i = 0; $i < $length; $i++) {
            $passphrase .= $characters[random_int(0, $charactersLength - 1)];
        }
        
        return $passphrase;
    }

    /**
     * Configure Composer packages.
     */
    private function configureComposer()
    {
        chdir($this->installPath);
        $returnCode = 0;
        $output = [];
        exec("composer install --no-interaction --optimize-autoloader 2>&1", $output, $returnCode);
        $this->logger->debug($output);
        return $returnCode === 0;
    }

    /**
     * Configure application cache.
     */
    private function configureCache($environment = 'prod')
    {
        chdir($this->installPath);
        $returnCode = 0;
        $output = [];
        exec("php bin/console cache:clear --env=$environment 2>&1", $output, $returnCode);
        $this->logger->debug($output);
        if ($returnCode !== 0) {
            return false;
        }
        
        exec("php bin/console cache:warmup --env=$environment 2>&1", $output, $returnCode);
        $this->logger->debug($output);
        return $returnCode === 0;
    }

    /**
     * Copy files to installation path.
     */
    private function copyFiles()
    {
        if (file_exists($this->installPath)) {
            throw new AlreadyExistException("Installation path already exists.");
        }
        $this->rcopy(dirname(__FILE__), $this->installPath);
    }

    /**
     * Create symlink to installation path.
     */
    private function symlinkFiles()
    {
        if (file_exists($this->installPath)) {
            throw new AlreadyExistException("Installation path already exists.");
        }
        if (!symlink(dirname(__FILE__), $this->installPath)) {
            throw new Exception("Failed to create symlink.");
        }
    }

    /**
     * Configure Apache virtual host.
     */
    private function configureApache($port, $serverName, $maxFilesize)
    {
        $vhostContent = "
<VirtualHost *:$port>
    ServerName $serverName
    DocumentRoot {$this->installPath}/public
    
    <Directory {$this->installPath}/public>
        AllowOverride All
        Require all granted
        
        # Enable .htaccess files
        Options -Indexes +FollowSymLinks
    </Directory>
    
    # Increase max upload size
    php_value upload_max_filesize {$maxFilesize}M
    php_value post_max_size {$maxFilesize}M
    
    ErrorLog \${APACHE_LOG_DIR}/remotelabz_error.log
    CustomLog \${APACHE_LOG_DIR}/remotelabz_access.log combined
</VirtualHost>
";
        
        $vhostFile = "/etc/apache2/sites-available/remotelabz.conf";
        if (!file_put_contents($vhostFile, $vhostContent)) {
            throw new Exception("Failed to write Apache configuration.");
        }
        
        // Enable site and required modules
        exec("a2enmod rewrite");
        exec("a2ensite remotelabz.conf");
        exec("systemctl reload apache2");
        
        return true;
    }

    /**
     * Configure Yarn packages.
     */
    private function configureYarn()
    {
        chdir($this->installPath);
        $output = [];
        $returnCode = 0;
        
        exec("yarn install 2>&1", $output, $returnCode);
        $this->logger->debug($output);
        if ($returnCode) {
            throw new Exception("Could not install Yarn packages.");
        }
        
        unset($output);
        exec("yarn encore prod 2>&1", $output, $returnCode);
        $this->logger->debug($output);
        if ($returnCode) {
            throw new Exception("Could not compile Yarn packages.");
        }
        
        unset($output);
        exec("php bin/console assets:install --symlink public --relative 2>&1", $output, $returnCode);
        $this->logger->debug($output);
        if ($returnCode) {
            throw new Exception("Could not symlink assets.");
        }
    }

    private function configureMessengerService()
    {
        chdir($this->installPath);
        if (file_exists('/etc/systemd/system/remotelabz.service')) {
            unlink('/etc/systemd/system/remotelabz.service');
        }
        $returnCode = symlink($this->installPath . '/bin/remotelabz.service', '/etc/systemd/system/remotelabz.service');
        if (!$returnCode) {
            throw new Exception("Could not symlink messenger service.");
        }
    }

    private function configureProxyService()
    {
        chdir($this->installPath);
        if (file_exists('/etc/systemd/system/remotelabz-proxy.service')) {
            unlink('/etc/systemd/system/remotelabz-proxy.service');
        }
        $returnCode = symlink($this->installPath . '/bin/remotelabz-proxy.service', '/etc/systemd/system/remotelabz-proxy.service');
        if (!$returnCode) {
            throw new Exception("Could not symlink proxy service.");
        }
    }

    private function configureGitVersionService()
    {
        chdir($this->installPath);
        if (file_exists('/etc/systemd/system/remotelabz-git-version-update.service')) {
            unlink('/etc/systemd/system/remotelabz-git-version-update.service');
        }
        $returnCode = symlink($this->installPath . '/bin/remotelabz-git-version-update.service', '/etc/systemd/system/remotelabz-git-version-update.service');
        if (!$returnCode) {
            throw new Exception("Could not symlink git version service.");
        }
    }

    private function configureGitVersionTimerService()
    {
        chdir($this->installPath);
        if (file_exists('/etc/systemd/system/remotelabz-git-version-update.timer')) {
            unlink('/etc/systemd/system/remotelabz-git-version-update.timer');
        }
        $returnCode = symlink($this->installPath . '/bin/remotelabz-git-version-update.timer', '/etc/systemd/system/remotelabz-git-version-update.timer');
        if (!$returnCode) {
            throw new Exception("Could not symlink git version timer service.");
        }
    }

    private function configureRouteMonitorService()
    {
        chdir($this->installPath);
        if (file_exists('/etc/systemd/system/remotelabz-route-monitor.service')) {
            unlink('/etc/systemd/system/remotelabz-route-monitor.service');
        }
        $returnCode = symlink($this->installPath . '/bin/remotelabz-route-monitor.service', '/etc/systemd/system/remotelabz-route-monitor.service');
        if (!$returnCode) {
            throw new Exception("Could not symlink route monitor service.");
        }
    }

    private function configureRouteMonitorTimerService()
    {
        chdir($this->installPath);
        if (file_exists('/etc/systemd/system/remotelabz-route-monitor.timer')) {
            unlink('/etc/systemd/system/remotelabz-route-monitor.timer');
        }
        $returnCode = symlink($this->installPath . '/bin/remotelabz-route-monitor.timer', '/etc/systemd/system/remotelabz-route-monitor.timer');
        if (!$returnCode) {
            throw new Exception("Could not symlink route monitor timer service.");
        }
    }

    private function configureCleanNotificationService()
    {
        chdir($this->installPath);
        if (file_exists('/etc/systemd/system/remotelabz-clean-notification.service')) {
            unlink('/etc/systemd/system/remotelabz-clean-notification.service');
        }
        $returnCode = symlink($this->installPath . '/bin/remotelabz-clean-notification.service', '/etc/systemd/system/remotelabz-clean-notification.service');
        if (!$returnCode) {
            throw new Exception("Could not symlink clean notification service.");
        }
    }

    private function configureCleanNotificationTimerService()
    {
        chdir($this->installPath);
        if (file_exists('/etc/systemd/system/remotelabz-clean-notification.timer')) {
            unlink('/etc/systemd/system/remotelabz-clean-notification.timer');
        }
        $returnCode = symlink($this->installPath . '/bin/remotelabz-clean-notification.timer', '/etc/systemd/system/remotelabz-clean-notification.timer');
        if (!$returnCode) {
            throw new Exception("Could not symlink clean notification timer service.");
        }
    }

    /**
     * Recursively copy a folder.
     */
    private function rcopy($src, $dst)
    {
        $this->logger->debug("Copy file from " . $src . " to " . $dst);
        $dir = opendir($src);
        @mkdir($dst);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->rcopy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    /**
     * Recursively change owner user and group of a folder.
     */
    function rchown($dir, $user, $group)
    {
        if (!($d = opendir($dir))) {
            throw new Exception("Error while opening directory ${dir}: Directory does not exist or is not reachable.");
        }
        while (false !== ($file = readdir($d))) {
            if (($file != ".") && ($file != "..")) {
                $path = $dir . "/" . $file;

                if (is_dir($path)) {
                    if (!chown($path, $user)) {
                        throw new Exception("Can't set permission of file ${path}: Permission refused or user does not exist.");
                    }
                    if (!chgrp($path, $group)) {
                        throw new Exception("Can't set permission of file ${path}: Permission refused or group does not exist.");
                    }
                    $this->rchown($path, $user, $group);
                } else {
                    if (!chown($path, $user)) {
                        throw new Exception("Can't set permission of file ${path}: Permission refused or user does not exist.");
                    }
                    if (!chgrp($path, $group)) {
                        throw new Exception("Can't set permission of file ${path}: Permission refused or group does not exist.");
                    }
                }
            }
        }
        closedir($d);
    }

    // Getters and Setters
    public function getLogger() { return $this->logger; }
    public function setLogger(Logger $logger) { $this->logger = $logger; return $this; }
    public function getInstallPath() { return $this->installPath; }
    public function setInstallPath(string $installPath) { $this->installPath = $installPath; return $this; }

    private function configure_db() {
        $returnCode = 0;
        $output = [];
        exec("/opt/remotelabz/bin/remotelabz-ctl reconfigure database", $output, $returnCode);
        
        if ($returnCode) {
            return false;
        }
        return true;
    }
}