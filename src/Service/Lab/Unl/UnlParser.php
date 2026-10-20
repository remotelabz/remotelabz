<?php

namespace App\Service\Lab\Unl;

use Psr\Log\LoggerInterface;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Parse un lab EVE-NG (fichier .unl, eventuellement zippe) en une structure
 * normalisee, independante du reste de l'application.
 *
 * La structure retournee est un tableau associatif :
 * [
 *   'lab' => ['name' => string, 'scripttimeout' => int],
 *   'nodes' => [
 *      ['eve_id'=>int, 'name'=>string, 'type'=>string, 'template'=>string,
 *       'image'=>string, 'icon'=>string, 'left'=>int, 'top'=>int, 'ram'=>int,
 *       'cpu'=>int, 'console'=>string, 'ethernet'=>int, 'config_data'=>string,
 *       'config'=>int,
 *       'interfaces'=>[['id'=>int,'name'=>string,'type'=>string,'network_id'=>int]]],
 *   ],
 *   'networks' => [['id'=>int,'type'=>string,'name'=>string,'left'=>int,'top'=>int,
 *                   'visibility'=>int,'icon'=>string]],
 *   'textobjects' => [['id'=>int,'name'=>string,'type'=>string,'data'=>string]],
 * ]
 */
class UnlParser
{
    private const SYSTEM_NETWORK_TYPES = ['nat0', 'nat1', 'pnet0', 'pnet1', 'cloud', 'system'];

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Parse un fichier sur le disque (.unl XML ou .zip contenant un .unl).
     */
    public function parseFile(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('EVE-NG import: file "%s" does not exist.', $path));
        }

        if ($this->isZip($path)) {
            return $this->parseZip($path);
        }

        $content = file_get_contents($path);
        if (false === $content) {
            throw new RuntimeException(sprintf('EVE-NG import: unable to read "%s".', $path));
        }

        return $this->parseXml($content);
    }

    /**
     * Parse un contenu deja en memoire (.unl XML).
     */
    public function parseContent(string $content): array
    {
        if (str_starts_with($content, 'PK')) {
            throw new RuntimeException('EVE-NG import: a zipped lab must be parsed through parseFile().');
        }

        return $this->parseXml($content);
    }

    /**
     * @return array{path: string, entry: string}|null
     */
    public function findUnlInZip(string $path): ?array
    {
        $zip = new ZipArchive();
        if (true !== $zip->open($path)) {
            return null;
        }

        $entry = null;
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $name = $zip->getNameIndex($i);
            if (null !== $name && !str_ends_with($name, '/') && preg_match('/\.unl$/i', $name)) {
                $entry = $name;
                break;
            }
        }
        $zip->close();

        if (null === $entry) {
            return null;
        }

        return ['path' => $path, 'entry' => $entry];
    }

    private function isZip(string $path): bool
    {
        if (preg_match('/\.zip$/i', $path)) {
            return true;
        }

        $handle = fopen($path, 'rb');
        if (false === $handle) {
            return false;
        }
        $magic = fread($handle, 2);
        fclose($handle);

        return 'PK' === $magic;
    }

    private function parseZip(string $path): array
    {
        $location = $this->findUnlInZip($path);
        if (null === $location) {
            throw new RuntimeException('EVE-NG import: the archive does not contain any .unl file.');
        }

        $zip = new ZipArchive();
        if (true !== $zip->open($path)) {
            throw new RuntimeException('EVE-NG import: unable to open the archive.');
        }

        $content = $zip->getFromName($location['entry']);
        $zip->close();

        if (false === $content) {
            throw new RuntimeException('EVE-NG import: unable to read the .unl inside the archive.');
        }

        $parsed = $this->parseXml($content);
        $parsed['lab']['archive_entry'] = $location['entry'];

        return $parsed;
    }

    private function parseXml(string $content): array
    {
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (false === $xml) {
            $message = 'EVE-NG import: invalid XML.';
            if (!empty($errors)) {
                $message .= ' ' . trim($errors[0]->message);
            }
            throw new RuntimeException($message);
        }

        $labAttributes = $xml->attributes();

        $parsed = [
            'lab' => [
                'name' => $this->string((string) ($labAttributes['name'] ?? 'EVE-NG lab')),
                'scripttimeout' => $this->int((string) ($labAttributes['scripttimeout'] ?? 300), 300),
            ],
            'nodes' => [],
            'networks' => [],
            'textobjects' => [],
        ];

        // --- Nodes ----------------------------------------------------------
        foreach ($xml->xpath('//topology/nodes/node') ?: [] as $node) {
            $parsed['nodes'][] = $this->parseNode($node);
        }

        // --- Networks -------------------------------------------------------
        foreach ($xml->xpath('//topology/networks/network') ?: [] as $network) {
            $attributes = $network->attributes();
            $parsed['networks'][] = [
                'id' => $this->int((string) ($attributes['id'] ?? 0)),
                'type' => $this->string((string) ($attributes['type'] ?? 'bridge')),
                'name' => $this->string((string) ($attributes['name'] ?? '')),
                'left' => $this->int((string) ($attributes['left'] ?? 0)),
                'top' => $this->int((string) ($attributes['top'] ?? 0)),
                'visibility' => $this->int((string) ($attributes['visibility'] ?? 0)),
                'icon' => $this->string((string) ($attributes['icon'] ?? '')),
            ];
        }

        // --- Text objects ---------------------------------------------------
        foreach ($xml->xpath('//objects/textobjects/textobject') ?: [] as $textobject) {
            $attributes = $textobject->attributes();
            $data = (string) ($textobject->data ?? '');
            $parsed['textobjects'][] = [
                'id' => $this->int((string) ($attributes['id'] ?? 0)),
                'name' => $this->string((string) ($attributes['name'] ?? 'text')),
                'type' => $this->string((string) ($attributes['type'] ?? 'text')),
                'data' => $this->decodeBase64($data),
            ];
        }

        if (empty($parsed['nodes'])) {
            $this->logger->warning('[UnlParser:parseXml]::No node found in the EVE-NG lab.');
        }

        return $parsed;
    }

    private function parseNode(SimpleXMLElement $node): array
    {
        $attributes = $node->attributes();

        $interfaces = [];
        foreach ($node->interface ?? [] as $interface) {
            $interfaceAttributes = $interface->attributes();
            $interfaces[] = [
                'id' => $this->int((string) ($interfaceAttributes['id'] ?? 0)),
                'name' => $this->string((string) ($interfaceAttributes['name'] ?? '')),
                'type' => $this->string((string) ($interfaceAttributes['type'] ?? 'ethernet')),
                'network_id' => $this->int((string) ($interfaceAttributes['network_id'] ?? 0)),
            ];
        }

        return [
            'eve_id' => $this->int((string) ($attributes['id'] ?? 0)),
            'name' => $this->string((string) ($attributes['name'] ?? '')),
            'type' => $this->string((string) ($attributes['type'] ?? 'qemu')),
            'template' => $this->string((string) ($attributes['template'] ?? '')),
            'image' => $this->string((string) ($attributes['image'] ?? '')),
            'icon' => $this->string((string) ($attributes['icon'] ?? '')),
            'left' => $this->int((string) ($attributes['left'] ?? 0)),
            'top' => $this->int((string) ($attributes['top'] ?? 0)),
            'ram' => $this->int((string) ($attributes['ram'] ?? 0)),
            'cpu' => $this->int((string) ($attributes['cpu'] ?? 1), 1),
            'console' => $this->string((string) ($attributes['console'] ?? '')),
            'ethernet' => $this->int((string) ($attributes['ethernet'] ?? count($interfaces))),
            'config' => $this->int((string) ($attributes['config'] ?? 0)),
            'config_data' => $this->decodeBase64((string) ($attributes['config_data'] ?? '')),
            'interfaces' => $interfaces,
        ];
    }

    /**
     * Un <network> systeme (nat/pnet/cloud) represente un acces Internet/externe
     * plutot qu'un simple segment L2.
     */
    public function isSystemNetwork(array $network): bool
    {
        return in_array(strtolower($network['type']), self::SYSTEM_NETWORK_TYPES, true)
            || 'bridge' !== strtolower($network['type']);
    }

    private function decodeBase64(string $value): string
    {
        $value = trim($value);
        if ('' === $value) {
            return '';
        }

        $decoded = base64_decode($value, true);
        if (false === $decoded) {
            // Certains champs ne sont pas encodes en base64 : on les garde tels quels.
            return $value;
        }

        // EVE-NG encode parfois les donnees textuelles en base64 d'URL-encode.
        if (str_contains($decoded, '%3C') || str_contains($decoded, '%3c')) {
            $decoded = urldecode($decoded);
        }

        // Toujours rendre un texte exploitable en UTF-8.
        if (!mb_check_encoding($decoded, 'UTF-8')) {
            $decoded = mb_convert_encoding($decoded, 'UTF-8', 'UTF-8');
        }

        return $decoded;
    }

    private function string(string $value): string
    {
        return trim($value);
    }

    private function int(string $value, int $default = 0): int
    {
        if ('' === trim($value) || !is_numeric($value)) {
            return $default;
        }

        return (int) $value;
    }
}
