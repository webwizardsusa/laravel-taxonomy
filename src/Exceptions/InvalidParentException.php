<?php

namespace Webwizardsusa\Taxonomy\Exceptions;

use InvalidArgumentException;

/**
 * A Parent Term that isn't allowed: in a flat or another Vocabulary, the Term itself, or one of its descendants.
 */
class InvalidParentException extends InvalidArgumentException {}
