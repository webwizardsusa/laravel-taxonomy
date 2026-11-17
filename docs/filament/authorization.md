---
title: Authorization
weight: 40
description: Control who can see the management pages and create, reorder, edit and delete Terms with a policy on the Term model.
---

The Filament pieces ask the policy registered for your configured Term model. Without a policy, everything is
allowed, which matches Filament's own default.

## Writing the policy

```php
namespace App\Policies;

use App\Models\User;
use Webwizardsusa\Taxonomy\Models\Term;
use Webwizardsusa\Taxonomy\Vocabulary;

class TermPolicy
{
    public function viewAny(User $user, Vocabulary $vocabulary): bool
    {
        return $user->is_admin;
    }

    public function create(User $user, Vocabulary $vocabulary): bool
    {
        return $vocabulary::key() === 'tags' || $user->is_admin;
    }

    public function reorder(User $user, Vocabulary $vocabulary): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Term $term): bool
    {
        return $user->is_admin;
    }

    public function delete(User $user, Term $term): bool
    {
        return $user->is_admin;
    }
}
```

Register it for the model in `taxonomy.term_model`. Laravel's policy discovery finds `App\Policies\TermPolicy` for
your own `App\Models\Term`, but not for the package's model, so register it yourself in that case:

```php
use Illuminate\Support\Facades\Gate;
use Webwizardsusa\Taxonomy\Models\Term;

Gate::policy(Term::class, \App\Policies\TermPolicy::class);
```

## The abilities

Vocabulary abilities receive the Vocabulary instance, so one policy can treat Vocabularies differently. Term
abilities receive the Term.

| Ability | Arguments | Checked by |
| --- | --- | --- |
| `viewAny` | `User`, `Vocabulary` | The management page: access and its navigation item |
| `create` | `User`, `Vocabulary` | The page's "New" action, and `TermTagsInput` when a typed name is new |
| `reorder` | `User`, `Vocabulary` | Dragging rows in the table, and dragging Terms in the tree |
| `update` | `User`, `Term` | The edit action |
| `delete` | `User`, `Term` | The delete action |

Hidden actions are also refused on the server: a tree move without `reorder` returns a 403.

When `create` is denied, `TermTagsInput` still lets users choose existing tags and only refuses new names, so authors
can tag posts without growing the Vocabulary.

> [!IMPORTANT]
> Once a policy exists, Laravel denies any ability the policy doesn't define. Define all five methods, or use a
> `before()` method, so you don't lock users out of the pages by accident.

## What isn't checked

The policy guards the Filament UI only. The model and the `HasTerms` methods don't consult it:
`$post->syncTerms('tags', ['New tag'])` creates the Term whatever the policy says. Check the policy yourself in
controllers and jobs that create or change Terms:

```php
use Illuminate\Support\Facades\Gate;
use Webwizardsusa\Taxonomy\Facades\Taxonomy;

Gate::authorize('create', [Taxonomy::termModel(), Taxonomy::vocabulary('tags')]);
Gate::authorize('update', $term);
```

`Gate` denies when no policy is registered. To get the package's behaviour (allowed without a policy), use
`Webwizardsusa\Taxonomy\Support\TermGate::allows($ability, $vocabularyOrTerm)`, which the Filament pieces use.
