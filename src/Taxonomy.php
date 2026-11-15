<?php

namespace Webwizardsusa\Taxonomy;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Webwizardsusa\Taxonomy\Exceptions\InvalidVocabularyException;
use Webwizardsusa\Taxonomy\Exceptions\UnknownVocabularyException;
use Webwizardsusa\Taxonomy\Models\Term;

/**
 * The registry of Vocabularies, keyed by the key each one declares, and the way to the configured Term model.
 */
class Taxonomy
{
    /**
     * @var array<string, class-string<Vocabulary>>
     */
    protected array $classes = [];

    /**
     * @var array<string, Vocabulary>
     */
    protected array $instances = [];

    public function __construct(protected Container $container) {}

    /**
     * @param  class-string<Vocabulary>  ...$classes
     */
    public function register(string ...$classes): void
    {
        foreach ($classes as $class) {
            if (! is_a($class, Vocabulary::class, true)) {
                throw new InvalidVocabularyException("[{$class}] isn't a Vocabulary: it must extend ".Vocabulary::class.'.');
            }

            $key = $class::key();

            if (isset($this->classes[$key]) && $this->classes[$key] !== $class) {
                throw new InvalidVocabularyException("[{$class}] and [{$this->classes[$key]}] both have the key [{$key}].");
            }

            $vocabulary = $this->container->make($class);

            if ($vocabulary->isOpen() && $vocabulary->isHierarchical()) {
                throw new InvalidVocabularyException('['.class_basename($class).'] is open, and open vocabularies are flat.');
            }

            $this->classes[$key] = $class;
            $this->instances[$key] = $vocabulary;
        }
    }

    /**
     * @param  string | Vocabulary  $vocabulary  a key, a Vocabulary class or an instance
     */
    public function vocabulary(string|Vocabulary $vocabulary): Vocabulary
    {
        $key = $this->keyFor($vocabulary);

        if (! isset($this->instances[$key])) {
            throw new UnknownVocabularyException("No vocabulary is registered with the key [{$key}].");
        }

        return $this->instances[$key];
    }

    public function has(string|Vocabulary $vocabulary): bool
    {
        return isset($this->instances[$this->keyFor($vocabulary)]);
    }

    /**
     * @return array<string, Vocabulary>
     */
    public function vocabularies(): array
    {
        return $this->instances;
    }

    /**
     * @return class-string<Term>
     */
    public function termModel(): string
    {
        $class = config('taxonomy.term_model', Term::class);

        if (! is_a($class, Term::class, true)) {
            throw new InvalidArgumentException("The taxonomy.term_model [{$class}] must extend ".Term::class.'.');
        }

        return $class;
    }

    /**
     * @return Builder<Term>
     */
    public function newTermQuery(): Builder
    {
        return $this->termModel()::query();
    }

    protected function keyFor(string|Vocabulary $vocabulary): string
    {
        if ($vocabulary instanceof Vocabulary) {
            return $vocabulary::key();
        }

        if (is_a($vocabulary, Vocabulary::class, true)) {
            return $vocabulary::key();
        }

        return $vocabulary;
    }
}
