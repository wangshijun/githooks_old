GitHooks
=========

Git Repostiroy Quality Assurance Tools

Installation
------------

* eslint https://github.com/eslint/eslint `npm install -g eslint`
* jshint https://github.com/jshint/jshint `npm install -g jshint`
* csshint https://github.com/stubbornella/csslint `npm install -g csshint`
* php code sniffer https://github.com/squizlabs/php_codesniffer `composer install`

Usage
-------------

``
git clone https://github.com/wangshijun/githooks
``

Under your git repo root, make symbol link

``
ln -sf /path/to/githooks-repo/hooks/pre-commit.php .git/hooks/pre-commit
``

Configuration
-------------

In your project folder, add following config files if neccessary

* .eslintrc
* .jshintrc
* .csshintrc
