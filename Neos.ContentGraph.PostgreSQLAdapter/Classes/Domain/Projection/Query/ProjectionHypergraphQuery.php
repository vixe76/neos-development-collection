<?php
declare(strict_types=1);

namespace Neos\ContentGraph\PostgreSQLAdapter\Domain\Projection\Query;

/*
 * This file is part of the Neos.ContentGraph.PostgreSQLAdapter package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\ResultStatement;
use Neos\ContentGraph\PostgreSQLAdapter\Domain\Projection\HierarchyHyperrelationRecord;
use Neos\ContentGraph\PostgreSQLAdapter\Domain\Projection\NodeRecord;
use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAddress\NodeAddress;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\OriginDimensionSpacePoint;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
final class ProjectionHypergraphQuery implements ProjectionHypergraphQueryInterface
{
    private string $query;

    private array $parameters;

    private function __construct($query, $parameters)
    {
        $this->query = $query;
        $this->parameters = $parameters;
    }

    public static function create(ContentStreamIdentifier $contentStreamIdentifier): self
    {
        $query = /** @lang PostgreSQL */
            'SELECT n.*
            FROM ' . HierarchyHyperrelationRecord::TABLE_NAME .' h
            JOIN ' . NodeRecord::TABLE_NAME .' n ON h.childnodeanchors @> jsonb_build_array(n.relationanchorpoint)::jsonb
            WHERE h.contentstreamidentifier = :contentStreamIdentifier';

        $parameters = [
            'contentStreamIdentifier' => (string)$contentStreamIdentifier
        ];

        return new self($query, $parameters);
    }

    public static function createForNodeAddress(NodeAddress $nodeAddress): self
    {
        $query = self::create($nodeAddress->getContentStreamIdentifier());
        return $query->withDimensionSpacePoint($nodeAddress->getDimensionSpacePoint())
            ->withNodeAggregateIdentifier($nodeAddress->getNodeAggregateIdentifier());
    }

    public function withDimensionSpacePoint(DimensionSpacePoint $dimensionSpacePoint): self
    {
        $query = $this->query .= '
            AND h.dimensionspacepointhash = :dimensionSpacePointHash';

        $parameters = $this->parameters;
        $parameters['dimensionSpacePointHash'] = $dimensionSpacePoint->getHash();

        return new self($query, $parameters);
    }

    public function withOriginDimensionSpacePoint(OriginDimensionSpacePoint $originDimensionSpacePoint): self
    {
        $query = $this->query .= '
            AND n.origindimensionspacepointhash = :originDimensionSpacePointHash';

        $parameters = $this->parameters;
        $parameters['originDimensionSpacePointHash'] = $originDimensionSpacePoint->getHash();

        return new self($query, $parameters);
    }

    public function withNodeAggregateIdentifier(NodeAggregateIdentifier $nodeAggregateIdentifier): self
    {
        $query = $this->query .= '
            AND n.nodeaggregateidentifier = :nodeAggregateIdentifier';

        $parameters = $this->parameters;
        $parameters['nodeAggregateIdentifier'] = (string)$nodeAggregateIdentifier;

        return new self($query, $parameters);
    }

    public function execute(Connection $databaseConnection): ResultStatement
    {
        return $databaseConnection->executeQuery($this->query, $this->parameters);
    }
}
