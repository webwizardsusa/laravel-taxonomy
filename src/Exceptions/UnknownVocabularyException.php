<?php

namespace Webwizardsusa\Taxonomy\Exceptions;

use InvalidArgumentException;

/**
 * No Vocabulary is registered with the key or class asked for.
 */
class UnknownVocabularyException extends InvalidArgumentException {}
