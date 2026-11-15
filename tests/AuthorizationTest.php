<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Webwizardsusa\Taxonomy\Facades\Taxonomy;
use Webwizardsusa\Taxonomy\Models\Term;
use Webwizardsusa\Taxonomy\Support\TermGate;
use Webwizardsusa\Taxonomy\Tests\TestCase;
use Webwizardsusa\Taxonomy\Vocabulary;

uses(TestCase::class);

class AuthorizationTermPolicy
{
    public function create(User $user, Vocabulary $vocabulary): bool
    {
        return $vocabulary::key() === 'tags';
    }

    public function update(User $user, Term $term): bool
    {
        return $term->name === 'Editable';
    }
}

it('allows everything when there is no term policy', function (): void {
    expect(TermGate::allows('create', Taxonomy::vocabulary('categories')))->toBeTrue()
        ->and(TermGate::allows('delete', Term::create(['vocabulary' => 'tags', 'name' => 'PHP'])))->toBeTrue();
});

it('asks the term policy with the vocabulary or the term', function (): void {
    Gate::policy(Term::class, AuthorizationTermPolicy::class);
    $this->actingAs(new User);

    expect(TermGate::allows('create', Taxonomy::vocabulary('tags')))->toBeTrue()
        ->and(TermGate::allows('create', Taxonomy::vocabulary('categories')))->toBeFalse()
        ->and(TermGate::allows('update', Term::create(['vocabulary' => 'tags', 'name' => 'Editable'])))->toBeTrue()
        ->and(TermGate::allows('update', Term::create(['vocabulary' => 'tags', 'name' => 'Locked'])))->toBeFalse();
});
