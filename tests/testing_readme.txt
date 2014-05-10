Unit test instructions:

To run the platform agnostic tests, run 'phpunit' with no arguments in this folder.

To run tests for the current platform, run 'phpunit os/Linux.php' or Darwin.php
or whichever you're running on.

This assumes you have phpunit installed somewhere in your $PATH. I just save
the phpunit.phar file to ~/bin/phpunit

For writing new tests, use two space indent. The rest of Linfo should be two
space indent but for legacy reasons it remains tabs.
