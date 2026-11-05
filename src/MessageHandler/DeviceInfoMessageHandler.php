<?php

namespace App\MessageHandler;

use App\Repository\DeviceInstanceRepository;
use App\Service\Instance\DeviceInfoCache;
use Psr\Log\LoggerInterface;
use Remotelabz\Message\Message\DeviceInfoMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Traite la reponse du worker (DeviceInfoMessage) : ecrit l'IP dans le cache
 * Redis et libere le verrou anti-spam.
 *
 * Consomme par "messenger:consume front" : ce handler ne doit jamais bloquer
 * un endpoint HTTP.
 */
#[AsMessageHandler]
class DeviceInfoMessageHandler
{
    public function __construct(
        private DeviceInfoCache $cache,
        private DeviceInstanceRepository $deviceInstanceRepository,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(DeviceInfoMessage $message): void
    {
        $uuid = $message->getUuid();

        if ($message->getError() !== null) {
            $this->logger->warning('[DeviceInfoMessageHandler]::' . $message->getError(), [
                'instance' => $uuid,
            ]);
            // Le worker a echoue : liberer le verrou pour autoriser un nouvel essai.
            $this->cache->release($uuid, $message->getRequestId());
            return;
        }

        $deviceInstance = $this->deviceInstanceRepository->findOneBy(['uuid' => $uuid]);
        $workerIp = null !== $deviceInstance && null !== $deviceInstance->getLabInstance()
            ? (string) $deviceInstance->getLabInstance()->getWorkerIp()
            : '';

        $this->cache->store($uuid, $message->getInfo(), $workerIp);
        $this->cache->release($uuid, $message->getRequestId());

        $this->logger->debug('[DeviceInfoMessageHandler]::IP received', [
            'instance' => $uuid,
            'workerIp' => $workerIp,
            'device_info' => $message->getInfo()
        ]);
    }
}
