<?php

declare(strict_types=1);

namespace EventSauce\LaravelEventSauce;

use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\AggregateRootRepository as EventSauceAggregateRootRepository;
use EventSauce\EventSourcing\ConstructingAggregateRootRepository;
use EventSauce\EventSourcing\MessageDispatcherChain;
use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\MessageDecoratorChain;
use Generator;
use LogicException;

abstract class AggregateRootRepository implements EventSauceAggregateRootRepository
{
	/** @var LaravelMessageRepository */
	private $messageRepository;

	/** @var string */
	protected $aggregateRoot;

	/** @var array */
	protected $consumers = [];

	/** @var array */
	protected $decorators = [];

	/** @var string */
	protected $connection;

	/** @var string */
	protected $table;

	/** @var string */
	protected static $inputFile = '';

	/** @var string */
	protected static $outputFile = '';

	public function __construct(LaravelMessageRepository $messageRepository)
	{
		if ($this->aggregateRoot === null || !is_a($this->aggregateRoot, AggregateRoot::class, true)) {
			throw new LogicException('You have to set an aggregate root before the repository can be initialized.');
		}

		$this->messageRepository = $messageRepository;

		if ($this->connection) {
			$this->messageRepository->setConnection($this->connection);
		}

		if ($this->table) {
			$this->messageRepository->setTable($this->table);
		}
	}

	public function retrieve(AggregateRootId $aggregateRootId): object
	{
		return $this->repository()->retrieve($aggregateRootId);
	}

	public function persist(object $aggregateRoot)
	{
		$this->repository()->persist($aggregateRoot);
	}

	public function persistEvents(AggregateRootId $aggregateRootId, int $aggregateRootVersion, object ...$events)
	{
		$this->repository()->persistEvents($aggregateRootId, $aggregateRootVersion, ...$events);
	}

	private function repository(): EventSauceAggregateRootRepository
	{
		return new ConstructingAggregateRootRepository(
			$this->aggregateRoot,
			$this->messageRepository,
			new MessageDispatcherChain(
				new LaravelMessageDispatcher(
					...$this->consumers
				),
				new EventMessageDispatcher()
			),
			new MessageDecoratorChain(
				...$this->instantiateDecorators()
			),
		);
	}

	public static function inputFile(): string
	{
		return static::$inputFile;
	}

	public static function outputFile(): string
	{
		return static::$outputFile;
	}

	private function instantiateDecorators(): Generator
	{
		// Ensure
		foreach ($this->decorators as $decorator) {
			yield app($decorator);
		}
	}
}
