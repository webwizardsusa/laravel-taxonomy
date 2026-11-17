# Changelog

## 0.1.0 (2026-11-17)


### Features

* add code-defined vocabularies, terms, the HasTerms trait, scopes and commands ([cbff598](https://github.com/webwizardsusa/laravel-taxonomy/commit/cbff598bb69842980d49ff16a7e67fca95cf01e9))
* add Filament fields, column, entry, filter and per-vocabulary management pages ([24b3ce2](https://github.com/webwizardsusa/laravel-taxonomy/commit/24b3ce2fe413202579137bff111dcb61f72d4ad5))
* report tags with a missing or stale name key in taxonomy:check ([c8efc20](https://github.com/webwizardsusa/laravel-taxonomy/commit/c8efc20a89caef61f700b82c49dc2b9d6c729b7c))
* set the management pages' navigation group and sort on the plugin ([6433a0c](https://github.com/webwizardsusa/laravel-taxonomy/commit/6433a0c9cec447db4dd31c831281794fe43c5e89))
* take term paths for closed vocabularies in syncTerms() and attachTerms() ([015d4a0](https://github.com/webwizardsusa/laravel-taxonomy/commit/015d4a03d05db4d8fa2d2ea3714ee20da323feb1))


### Bug Fixes

* find the page configuration through the default panel when none is current ([181ab52](https://github.com/webwizardsusa/laravel-taxonomy/commit/181ab529113bbce6e8672a7bdabeb9b769948473))
* match only top-level terms by slug in hierarchical vocabularies ([dad4178](https://github.com/webwizardsusa/laravel-taxonomy/commit/dad41782a118bb6268d9ed97303b773a5fb1a970))
* read the terms to detach the way syncTerms() and attachTerms() read them ([d663d39](https://github.com/webwizardsusa/laravel-taxonomy/commit/d663d39954cff4b8c325182071868f92fe05ae7e))
* remove the unused validation.wrong_vocabulary translation ([455649d](https://github.com/webwizardsusa/laravel-taxonomy/commit/455649dda307557f560650d8f1970601afe055d3))
* render action modals on the category tree, and name its modals after the vocabulary ([b9a1396](https://github.com/webwizardsusa/laravel-taxonomy/commit/b9a1396b4b9c6ad22647318c801c5f6a6359f52e))
* show no terms in TermsColumn for records without HasTerms ([132c866](https://github.com/webwizardsusa/laravel-taxonomy/commit/132c8666380b6b9801afe82a69e773fd43211919))


### Miscellaneous Chores

* scaffold the package ([d1d9c30](https://github.com/webwizardsusa/laravel-taxonomy/commit/d1d9c309ca82267ae913da70d6804d3f1b72d58f))
