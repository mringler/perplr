<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Tests\Runtime;

use Propel\Runtime\Perpl;
use Propel\Runtime\ServiceContainer\ServiceContainer;
use Propel\Runtime\ServiceContainer\ServiceContainerInterface;
use Propel\Tests\Helpers\BaseTestCase;
use Propel\Runtime\Exception\PropelException;
use Psr\Log\LoggerInterface;

class PerplTest extends BaseTestCase
{
    protected static $initialServiceContainer;
    
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::$initialServiceContainer = Perpl::getServiceContainer();
    }
    
    public function tearDown(): void
    {
        Perpl::setServiceContainer(static::$initialServiceContainer);
    }
    
    /**
     * @return void
     */
    public function testGetServiceContainerReturnsAServiceContainer()
    {
        $this->assertInstanceOf(ServiceContainer::class, Perpl::getServiceContainer());
    }

    /**
     * @return void
     */
    public function testGetServiceContainerAlwaysReturnsTheSameInstance()
    {
        $sc1 = Perpl::getServiceContainer();
        $sc2 = Perpl::getServiceContainer();
        $this->assertSame($sc1, $sc2);
    }

    /**
     * @return void
     */
    public function testSetServiceContainerOverridesTheExistingServiceContainer()
    {
        $newSC = new ServiceContainer();
        Perpl::setServiceContainer($newSC);
        $this->assertSame($newSC, Perpl::getServiceContainer());
    }
    
    public function testGetStandardServiceContainerWithDefaultContainer()
    {
        $sc = Perpl::getStandardServiceContainer();
        $this->assertInstanceOf(ServiceContainer::class, $sc);
    }

    public function testGetServiceContainerSetsInstance(): void
    {
        $this->setObjectPropertyValue(null, 'serviceContainer', null, Perpl::class);
        Perpl::getServiceContainer();
        $sc = $this->getObjectPropertyValue(Perpl::class, 'serviceContainer');
        $this->assertInstanceOf(ServiceContainer::class, $sc);
    }

    /**
     * @return array<array{string, ?array}>
     */
    public static function ServiceContainerFacadedMethodsProvider(): array
    {
        return [
            ['getDatabaseMap'],
            ['getAdapter'],
            ['getDefaultDatasource'],
            ['getConnectionManager', ['foo']],
            ['closeConnections'],
            ['getConnection'],
            ['getWriteConnection', ['foo']],
            ['getReadConnection', ['foo']],
            ['getProfiler'],
            ['getLogger'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ServiceContainerFacadedMethodsProvider')]
    public function testGetDatabaseMap(string $facedMethodName, array $args = []): void
    {
        $sc = $this->createMock(ServiceContainer::class);
        $sc->expects($this->once())
            ->method($facedMethodName);
        Perpl::setServiceContainer($sc);
        Perpl::$facedMethodName(...$args);
    }

    /**
     * @return array<array{int, string}>
     */
    public static function LogLevelProvider(): array
    {
        return [
            [Perpl::LOG_ALERT, 'alert'],
            [Perpl::LOG_CRIT, 'critical'],
            [Perpl::LOG_DEBUG, 'debug'],
            [Perpl::LOG_EMERG, 'emergency'],
            [Perpl::LOG_ERR, 'error'],
            [Perpl::LOG_INFO, 'info'],
            [Perpl::LOG_NOTICE, 'notice'],
            [Perpl::LOG_WARNING, 'warning'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('LogLevelProvider')]
    public function testLogLevels(int $logLevel, string $loggerMethodName): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method($loggerMethodName);

        $sc = $this->createMock(ServiceContainer::class);
        $sc->expects($this->once())
            ->method('getLogger')->willReturn($logger);

        Perpl::setServiceContainer($sc);
        Perpl::log('', $logLevel);
    }

    public function testClassAlias(): void
    {
        $this->assertTrue(class_exists('\Propel\Runtime\Propel'));
        $this->assertInstanceOf(Perpl::class, new \Propel\Runtime\Propel());
    }
}
