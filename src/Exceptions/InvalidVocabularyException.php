<?php

namespace Webwizardsusa\Taxonomy\Exceptions;

use InvalidArgumentException;

/**
 * A Vocabulary class that can't be registered: not a Vocabulary, a key that's taken, or open and hierarchical at once.
 */
class InvalidVocabularyException extends InvalidArgumentException {}
