<?php

namespace Webwizardsusa\Taxonomy\Exceptions;

use RuntimeException;

/**
 * A Term whose name or slug is already taken: anywhere in an Open Vocabulary, or among its siblings in a Closed one.
 * `$attribute` says which (`name` or `slug`).
 */
class DuplicateTermException extends RuntimeException
{
    public function __construct(string $message, public readonly string $attribute)
    {
        parent::__construct($message);
    }
}
