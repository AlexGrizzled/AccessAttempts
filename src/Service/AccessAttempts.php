<?php

namespace AlexGrizzled\Service;

use AlexGrizzled\Type\AttemptType;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class AccessAttempts implements ServiceSubscriberInterface
{
    private CacheInterface $cache;
    private ContainerInterface $container;
    private LockFactory $lockFactory;
    private AttemptType $attemptData;
    private string $resourceName;
    private string $parameterMaxName;
    private string $parameterIntervalName;
    private int $attemptMax = 3;
    private int $attemptInterval = 600;

    public function __construct(
        CacheInterface $cache,
        ContainerInterface $container,
        LockFactory $lockFactory
    ) {
        $this->cache = $cache;
        $this->container = $container;
        $this->lockFactory = $lockFactory;
    }

    /**
     * @param string $resourceName Имя ресурса, где будут храниться cache и lock
     * @param string $uniqueValue Уникальное значение, на пример IP адрес клиента или идентификатор пользователя (userId, email)
     * @param bool $summarize Не обязательный параметр, по умолчанию true - будет суммировать попытку
     * @return bool Вернёт булево значение, если много одновременных запросов или исчерпанных попыток - вернёт false иначе true
     */
    public function has(string $resourceName, string $uniqueValue, bool $summarize = true): bool
    {
        $this->resourceName = $resourceName . '-access-to-attempts-by-' . $uniqueValue;
        $this->parameterMaxName = $resourceName . '.attempt.max';
        $this->parameterIntervalName = $resourceName . '.attempt.interval';

        $lock = $this->lockFactory->createLock($this->resourceName);

        if (false === $lock->acquire()) {
            return false;
        }

        $this->loadParameters();
        $this->loadAttemptData();

        return $this->hasAttempt($summarize);
    }

    /**
     * @return int Возвращает количество попыток
     */
    public function getCount(): int
    {
        return $this->attemptData->getCounter();
    }

    /**
     * @return int Возвращает остаток секунд до обнуления счётчика попыток
     */
    public function getExpires(): int
    {
        return $this->attemptData->getStart() - time() + $this->attemptInterval;
    }

    private function loadParameters()
    {
        $this->attemptMax = $this->getParameter($this->parameterMaxName) ?: $this->attemptMax;
        $this->attemptInterval = $this->getParameter($this->parameterIntervalName) ?: $this->attemptInterval;
    }

    private function loadAttemptData(): void
    {
        $this->attemptData = $this->cache->get($this->resourceName, function (ItemInterface $item) {
            $item->expiresAfter($this->attemptInterval);

            return AttemptType::create();
        });
    }

    private function hasAttempt(bool $summarize): bool
    {
        $result = $this->attemptData->getCounter() < $this->attemptMax;
        if ($summarize) {
            $this->incAttemptCounter();
        }

        return $result;
    }

    private function incAttemptCounter()
    {
        $this->attemptData->inc();
        $this->cache->delete($this->resourceName);
        $this->cache->get($this->resourceName, function (ItemInterface $item) {
            $item->expiresAfter($this->getExpires());

            return $this->attemptData;
        });
    }

    private function getParameter(string $name)
    {
        if ($this->container->get('parameter_bag')->has($name)) {
            return $this->container->get('parameter_bag')->get($name);
        }

        return null;
    }

    public static function getSubscribedServices(): array
    {
        return [
            'parameter_bag' => '?' . ContainerBagInterface::class,
        ];
    }
}
