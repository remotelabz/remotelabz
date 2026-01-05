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
            throw new Exception("Error configuring RemoteLabz services: " . $e->getMessage());
        }

        // Step 9: Enable services
        echo "🔨 Enabling RemoteLabz services... ";
        try {
            $this->enableServices();
            echo "OK ✔️\n";
        } catch (Exception $e) {
            throw new Exception("Error enabling RemoteLabz services: " . $e->getMessage());
        }

        // Step 10: Configure sudoers
        echo "👮 Configuring sudoers file... ";
        try {
            copy("config/system/sudoers", "/etc/sudoers.d/remotelabz");
            echo "OK ✔️\n";
        } catch (Exception $e) {
            throw new Exception("Error configuring sudoers: " . $e->getMessage());
        }

        // Step 11: Configure directory permissions
        echo "👮 Configuring directory permissions... ";
        try {
            $this->configureDirectoryPermissions();
            echo "OK ✔️\n";
        } catch (Exception $e) {
            throw new Exception("Error configuring directory permissions: " . $e->getMessage());
        }

        $this->logger->debug("Finished RemoteLabz application installation");
        echo "\n";
        Logger::print("========================================\n", Logger::COLOR_GREEN);
        Logger::print("  Installation Complete!\n", Logger::COLOR_GREEN);
        Logger::print("========================================\n", Logger::COLOR_GREEN);
        echo "\n";
    }

    /**
     * Enable all RemoteLabz systemd services
     */
    private function enableServices()
    {
        $services = [
            'remotelabz',
            'remotelabz-proxy',
            'remotelabz-clean-notification.timer',
            'remotelabz-route-monitor.timer',
            'remotelabz-route-monitor.service'
        ];

        foreach ($services as $service) {
            $returnCode = 0;
            $output = [];
            exec("systemctl enable $service 2>&1", $output, $returnCode);
            $this->logger->debug("Enable $service: " . implode("\n", $output));
            if ($returnCode) {
                throw new Exception("Could not enable $service service.");
            }
        }
    }

    /**
     * Configure directory structure and permissions
     */
    private function configureDirectoryPermissions()
    {
        // Create upload directories
        @mkdir($this->installPath . "/public/uploads");
        @mkdir($this->installPath . "/public/uploads/lab");
        @mkdir($this->installPath . "/public/uploads/user");
        @mkdir($this->installPath . "/public/uploads/iso");
        
        // Set ownership
        $this->rchown($this->installPath . "/public/uploads", "www-data", "www-data");
        $this->rchown($this->installPath . "/var", "www-data", "www-data");
        
        // Set specific permissions
        chmod($this->installPath . "/config/packages/messenger.yaml", 0664);

        // Create templates directory if it doesn't exist
        if (!file_exists($this->installPath . "/config/templates")) {
            mkdir($this->installPath . "/config/templates", 0775, true);
        }
        
        $this->rchown($this->installPath . "/config/templates", "www-data", "www-data");
        chmod($this->installPath . "/config/templates", 0774);
        
        // Create backups directory
        @mkdir($this->installPath . "/backups");
        chmod($this->installPath . "/backups", 0775);
    }

    /**
     * Copy current directory to target installation directory.
     */
    private function copyFiles(): void
    {
        $isCopied = true;
        
        // Check if directory is already in the right place
        if (dirname(__FILE__, 3) != $this->installPath) {
            // Check if there is already a directory
            $this->rcopy(dirname(__FILE__, 3), $this->installPath);
            $isCopied = true;            
        } else {
            $isCopied = false;
        }

        if (!$isCopied) {
            throw new AlreadyExistException("Folder already exists.");
        }
    }

    /**
     * Symlink current directory to target installation directory.
     */
    private function symlinkFiles(): void
    {
        // Check if there is already a symlink
        if (is_link($this->installPath)) {
            $isCopied = false;
        } else {
            symlink(dirname(__FILE__, 3), $this->installPath);
            $isCopied = true;
        }

        if (!$isCopied) {
            throw new AlreadyExistException("Symlink already exists.");
        }
    }

    /**
     * Handle Composer packages installation.
     */
    private function configureComposer(): bool
    {
        chdir($this->installPath);
        $returnCode = 0;
        $output = [];
        exec("COMPOSER_ALLOW_SUPERUSER=1 composer install --no-progress 2>&1", $output, $returnCode);
        $this->logger->debug($output);
        if ($returnCode) {
            return false;
        }
        return true;
    }

    /**
     * Warm application cache.
     */
    private function configureCache($environment): bool
    {
        chdir($this->installPath);
        $returnCode = 0;
        $output = [];
        exec("php " . $this->installPath . "/bin/console cache:warm -e " . $environment . " 2>&1", $output, $returnCode);
        $this->logger->debug($output);
        if ($returnCode) {
            return false;
        }
        return true;
    }

    /**
     * Configure Apache for RemoteLabz.
     * Note: Basic Apache configuration should already be done by bash script.
     */
    private function configureApache($port, $serverName, $uploadMaxFilesize)
    {
        $output = [];
        $returnCode = 0;
        chdir($this->installPath);
        
        // Check if port is configured
        $portsFileContent = file_get_contents("/etc/apache2/ports.conf");
        if (preg_match("/Listen ${port}$/m", $portsFileContent) === 1) {
            $this->logger->debug("Port ${port} is already configured in Apache.");
        } else {
            file_put_contents("/etc/apache2/ports.conf", "\nListen ${port}\n", FILE_APPEND);
        }
        
        // Copy site configuration files
        copy($this->installPath . "/config/apache/ports.conf", "/etc/apache2/ports.conf");
        copy($this->installPath . "/config/apache/100-remotelabz.conf", "/etc/apache2/sites-available/100-remotelabz.conf");
        copy($this->installPath . "/config/apache/200-remotelabz-ssl.conf", "/etc/apache2/sites-available/200-remotelabz-ssl.conf");
        copy($this->installPath . "/config/apache/remotelabz-git.conf", "/etc/apache2/conf-enabled/remotelabz-git.conf");
        
        // Enable sites
        if (!is_file("/etc/apache2/sites-enabled/100-remotelabz.conf")) {
            symlink("/etc/apache2/sites-available/100-remotelabz.conf", "/etc/apache2/sites-enabled/100-remotelabz.conf");
        }
        if (!is_file("/etc/apache2/sites-enabled/200-remotelabz-ssl.conf")) {
            symlink("/etc/apache2/sites-available/200-remotelabz-ssl.conf", "/etc/apache2/sites-enabled/200-remotelabz-ssl.conf");
        }

        // Handle PHP max upload filesize
        $phpPath = str_replace(["cli/conf.d/20-", ",", "\n"], ["mods-available/", "", ""], shell_exec("php --ini | grep fileinfo"));
        $postMaxSize = intval(intval(substr($uploadMaxFilesize, 0, -1)) * 1.25);
        $ini = parse_ini_file($phpPath);
        
        // Update upload_max_filesize
        if (array_key_exists("upload_max_filesize", $ini)) {
            $content = file_get_contents($phpPath);
            $content = preg_replace("/^(upload_max_filesize=)(.*)$/m", "$1" . $uploadMaxFilesize, $content);
            file_put_contents($phpPath, $content);
        } else {
            file_put_contents($phpPath, "\nupload_max_filesize=" . $uploadMaxFilesize . "\n", FILE_APPEND);
        }
        
        // Update post_max_size
        if (array_key_exists("post_max_size", $ini)) {
            $content = file_get_contents($phpPath);
            $content = preg_replace("/^(post_max_size=)(.*)$/m", "$1" . $postMaxSize, $content);
            file_put_contents($phpPath, $content);
        } else {
            file_put_contents($phpPath, "post_max_size=" . $postMaxSize . substr($uploadMaxFilesize, -1), FILE_APPEND);
        }

        // Disable default site
        exec("a2dissite 000-default 2>&1", $output);
        $this->logger->debug($output);
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
}