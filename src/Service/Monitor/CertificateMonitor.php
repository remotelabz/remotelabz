<?php

namespace App\Service\Monitor;

use DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class CertificateMonitor implements ServiceMonitorInterface
{
    private $logger;
    private $sslCaKey;
    private $sslCaCert;
    private $sslTlsKey;
    private $remotelabzProxySslKey;
    private $remotelabzProxySslCert;
    private $warningDays;

    public function __construct(
        string $sslCaKey,
        string $sslCaCert,
        string $sslTlsKey,
        string $remotelabzProxySslKey,
        string $remotelabzProxySslCert,
        int $warningDays = 30,
        LoggerInterface $logger = null
    ) {
        $this->sslCaKey = $sslCaKey;
        $this->sslCaCert = $sslCaCert;
        $this->sslTlsKey = $sslTlsKey;
        $this->remotelabzProxySslKey = $remotelabzProxySslKey;
        $this->remotelabzProxySslCert = $remotelabzProxySslCert;
        $this->warningDays = $warningDays;
        $this->logger = $logger ?: new NullLogger();
    }

    public static function getServiceName(): string
    {
        return 'certificate-check';
    }

    /**
     * Check all certificates validity
     * Returns array with certificate statuses
     */
    public function isStarted()
    {
        $results = [
            'vpn_ca_cert' => $this->checkCertificate($this->sslCaCert, 'VPN CA Certificate'),
            'vpn_ca_key' => $this->checkKeyFile($this->sslCaKey, 'VPN CA Key'),
            'vpn_tls_key' => $this->checkKeyFile($this->sslTlsKey, 'VPN TLS Key'),
            'proxy_ssl_cert' => $this->checkCertificate($this->remotelabzProxySslCert, 'Proxy SSL Certificate'),
            'proxy_ssl_key' => $this->checkKeyFile($this->remotelabzProxySslKey, 'Proxy SSL Key'),
        ];

        // Overall status: true if all are valid
        $overallStatus = true;
        foreach ($results as $result) {
            if (!$result['valid']) {
                $overallStatus = false;
                break;
            }
        }

        return [
            'overall_status' => $overallStatus,
            'certificates' => $results
        ];
    }

    /**
     * Check a certificate file
     */
    private function checkCertificate(string $certPath, string $certName): array
    {
        $result = [
            'name' => $certName,
            'path' => $certPath,
            'exists' => false,
            'readable' => false,
            'valid' => false,
            'expires_at' => null,
            'days_remaining' => null,
            'is_expired' => null,
            'warning' => false,
            'error' => null
        ];

        try {
            // Check if file exists
            if (!file_exists($certPath)) {
                $result['error'] = 'File does not exist';
                $this->logger->warning("{$certName}: File does not exist at {$certPath}");
                return $result;
            }
            $result['exists'] = true;

            // Check if file is readable
            if (!is_readable($certPath)) {
                $result['error'] = 'File is not readable';
                $this->logger->warning("{$certName}: File is not readable at {$certPath}");
                return $result;
            }
            $result['readable'] = true;

            // Read certificate
            $certContent = file_get_contents($certPath);
            $certData = openssl_x509_parse($certContent);

            if ($certData === false) {
                $result['error'] = 'Failed to parse certificate';
                $this->logger->error("{$certName}: Failed to parse certificate at {$certPath}");
                return $result;
            }

            // Get expiration date
            $expiryTimestamp = $certData['validTo_time_t'];
            $expiryDate = new DateTime('@' . $expiryTimestamp);
            $now = new DateTime();
            $daysRemaining = $now->diff($expiryDate)->days;

            $result['expires_at'] = $expiryDate->format('Y-m-d H:i:s');
            $result['days_remaining'] = $daysRemaining;
            $result['is_expired'] = $expiryTimestamp < time();

            if ($result['is_expired']) {
                $result['error'] = 'Certificate has expired';
                $result['valid'] = false;
                $this->logger->error("{$certName}: Certificate has expired on {$result['expires_at']}");
            } elseif ($daysRemaining <= $this->warningDays) {
                $result['warning'] = true;
                $result['valid'] = true;
                $this->logger->warning("{$certName}: Certificate expires in {$daysRemaining} days");
            } else {
                $result['valid'] = true;
                $this->logger->info("{$certName}: Certificate is valid for {$daysRemaining} more days");
            }

        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            $this->logger->error("{$certName}: Error checking certificate: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Check a key file (just existence and readability)
     */
    private function checkKeyFile(string $keyPath, string $keyName): array
    {
        $result = [
            'name' => $keyName,
            'path' => $keyPath,
            'exists' => false,
            'readable' => false,
            'valid' => false,
            'error' => null
        ];

        try {
            // Check if file exists
            if (!file_exists($keyPath)) {
                $result['error'] = 'File does not exist';
                $this->logger->warning("{$keyName}: File does not exist at {$keyPath}");
                return $result;
            }
            $result['exists'] = true;

            // Check if file is readable
            if (!is_readable($keyPath)) {
                $result['error'] = 'File is not readable';
                $this->logger->warning("{$keyName}: File is not readable at {$keyPath}");
                return $result;
            }
            $result['readable'] = true;

            // Check if it looks like a key file
            $keyContent = file_get_contents($keyPath);
            if (strpos($keyContent, '-----BEGIN') !== false || strpos($keyContent, '-----END') !== false) {
                $result['valid'] = true;
                $this->logger->info("{$keyName}: Key file is valid");
            } else {
                $result['error'] = 'File does not appear to be a valid key';
                $this->logger->warning("{$keyName}: File does not appear to be a valid key");
            }

        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            $this->logger->error("{$keyName}: Error checking key file: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Not applicable for this monitor
     */
    public function start()
    {
        $this->logger->info("Certificate Monitor: start() is not applicable");
        return true;
    }

    /**
     * Not applicable for this monitor
     */
    public function stop()
    {
        $this->logger->info("Certificate Monitor: stop() is not applicable");
        return true;
    }

    /**
     * Get certificates that need attention (expired or expiring soon)
     */
    public function getCertificatesNeedingAttention(): array
    {
        $status = $this->isStarted();
        $needsAttention = [];

        foreach ($status['certificates'] as $key => $cert) {
            if (!$cert['valid'] || (isset($cert['warning']) && $cert['warning'])) {
                $needsAttention[$key] = $cert;
            }
        }

        return $needsAttention;
    }
}