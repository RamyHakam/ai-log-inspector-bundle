<?php

namespace Hakam\AiLogInspectorBundle\Tests\Unit\Store;

use PHPUnit\Framework\TestCase;
use Hakam\AiLogInspectorBundle\Store\LogStoreIndexer;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\Vectorizer;
use Symfony\AI\Store\StoreInterface;
use Symfony\Component\Uid\Uuid;

class LogStoreIndexerTest extends TestCase
{
    public function testIndexIgnoresEmptyEntries(): void
    {
        $vectorizer = $this->createMock(Vectorizer::class);
        $store = $this->createMock(StoreInterface::class);

        $store->expects($this->never())
            ->method('add');

        $indexer = new LogStoreIndexer($vectorizer, $store);
        $indexer->index([['id' => Uuid::v1(), 'metadata' => ['level' => 'warning']]]);
    }

    public function testIndexAddsDocuments(): void
    {
        $text = 'Error: DB connection timeout';
        $vectorizer = $this->createMock(Vectorizer::class);
        $store = $this->createMock(StoreInterface::class);

        $vectorizer->expects($this->once())
            ->method('vectorizeDocuments')
            ->willReturn([]);

        $store->expects($this->once())
            ->method('add');

        $indexer = new LogStoreIndexer($vectorizer, $store);
        $indexer->index([
            [
                'id' => Uuid::v1(),
                'content' => $text,
                'metadata' => new Metadata(['level' => 'error'])
            ]]);
    }
}
