<?php

declare(strict_types=1);

namespace Tests\MartinGeorgiev\Doctrine\ORM\Query\AST\Functions;

use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected const FIXTURES_DIRECTORY = __DIR__.'/../../../../Fixtures';

    /**
     * @var Configuration
     */
    private $configuration;

    protected function setUp(): void
    {
        parent::setUp();

        $configuration = new Configuration();
        $configuration->setProxyDir(static::FIXTURES_DIRECTORY.'/Proxies');
        $configuration->setProxyNamespace('Tests\MartinGeorgiev\Doctrine\Fixtures\Proxies');
        $configuration->setAutoGenerateProxyClasses(true);
        $configuration->setMetadataDriverImpl($configuration->newDefaultAnnotationDriver([static::FIXTURES_DIRECTORY.'/Entities']));
        $this->setConfigurationCache($configuration);

        $this->configuration = $configuration;

        $this->registerFunction();
    }

    private function setConfigurationCache(Configuration $configuration): void
    {
        $symfonyArrayAdapterClass = '\Symfony\Component\Cache\Adapter\ArrayAdapter';
        $useDbalV3 = \class_exists($symfonyArrayAdapterClass) && \method_exists($configuration, 'setMetadataCache') && \method_exists($configuration, 'setQueryCache');
        if ($useDbalV3) {
            // @phpstan-ignore-next-line
            $configuration->setMetadataCache(new $symfonyArrayAdapterClass());
            // @phpstan-ignore-next-line
            $configuration->setQueryCache(new $symfonyArrayAdapterClass());

            return;
        }
        $doctrineArrayCacheClass = '\Doctrine\Common\Cache\ArrayCache';
        $useDbalV2 = \class_exists($doctrineArrayCacheClass) && \method_exists($configuration, 'setMetadataCacheImpl') && \method_exists($configuration, 'setQueryCacheImpl');
        if ($useDbalV2) {
            // @phpstan-ignore-next-line
            $configuration->setMetadataCacheImpl(new $doctrineArrayCacheClass());
            // @phpstan-ignore-next-line
            $configuration->setQueryCacheImpl(new $doctrineArrayCacheClass());

            return;
        }

        throw new \RuntimeException('No known compatible version of doctrine/dbal found. Please report an issue on GitHub.');
    }

    private function registerFunction(): void
    {
        foreach ($this->getStringFunctions() as $dqlFunction => $functionClass) {
            $this->configuration->addCustomStringFunction($dqlFunction, $functionClass);
        }
    }

    /**
     * @return array<string, string>
     */
    protected function getStringFunctions(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    abstract protected function getExpectedSqlStatements(): array;

    /**
     * @return array<int, string>
     */
    abstract protected function getDqlStatements(): array;

    /**
     * @test
     */
    public function dql_is_transformed_to_valid_sql(): void
    {
        $expectedSqls = $this->getExpectedSqlStatements();
        $dqls = $this->getDqlStatements();
        if (\count($expectedSqls) !== \count($dqls)) {
            throw new \LogicException(\sprintf('You need ot provide matching expected SQL for every DQL, currently there are %d SQL statements for %d DQL statements', \count($expectedSqls), \count($dqls)));
        }
        foreach ($expectedSqls as $key => $expectedSql) {
            $this->assertSqlFromDql($expectedSql, $dqls[$key], \sprintf('Assertion failed for expected SQL statement "%s"', $expectedSql));
        }
    }

    private function assertSqlFromDql(string $expectedSql, string $dql, string $message = ''): void
    {
        $query = $this->buildEntityManager()->createQuery($dql);
        $this->assertEquals($expectedSql, $query->getSQL(), $message);
    }

    private function buildEntityManager(): EntityManager
    {
        return EntityManager::create(['driver' => 'pdo_sqlite', 'memory' => true], $this->configuration);
    }
}
