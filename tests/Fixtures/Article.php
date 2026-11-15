<?php

namespace Webwizardsusa\Taxonomy\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Webwizardsusa\Taxonomy\Concerns\HasTerms;

class Article extends Model
{
    use HasTerms;

    protected $guarded = [];
}
