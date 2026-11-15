# Taxonomy

Organizing records with Terms drawn from Vocabularies, both by free tagging and by curated categories.

## Language

**Vocabulary**:
A named set of Terms, such as "Tags" or "Categories", defined by a developer in code and registered with the application. Vocabularies can't be created or deleted at runtime.
_Avoid_: Taxonomy (for a single set), group, type

**Term**:
A single label within one Vocabulary that records can be tagged with. A Term belongs to exactly one Vocabulary.
_Avoid_: Tag (except for a Term in an Open Vocabulary), category item

**Open Vocabulary**:
A Vocabulary whose Terms can be created by typing them while tagging a record (free tagging). It is flat, and Term names are unique within it, ignoring case.
_Avoid_: Tagging vocabulary, freetags

**Closed Vocabulary**:
A Vocabulary whose Terms can only be chosen from those already created by an administrator (categories). Two Terms may share a name, but not under the same Parent Term.
_Avoid_: Category vocabulary, fixed list

**Parent Term**:
The single Term that a Term sits under in a hierarchical Vocabulary. Parent and child always share a Vocabulary.

**Term Path**:
The slugs from the top-level Term down to a Term, such as `cars/accessories`, which identify it within its Vocabulary.
_Avoid_: Full slug, breadcrumb

**Record**:
Any application model that can be tagged with Terms.
_Avoid_: Entity, node, taggable
