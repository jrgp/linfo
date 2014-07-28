 ## Goals

 - Call very few external programs (like df/load/uptime/etc), if any. (parse
   the file system for info, if possible)
 - Linux version does not use external programs *at all* and rely exclusively on
   /proc and /sys and connecting to locally listening daemons. (except for extensions, listed below)
 - Don't go nuts with eye candy. Don't use ajax. Make viewing the info on
   text only browsers possible and easy.
 - Any javascript ever used must degrade gracefully
 - Don't give info that can be exploited and turned into a security risk, especially
   not names and command line arguments given to running programs.
 - Don't use blatantly slow methods such as preg_split(), especially not in loops

## Code style formatting

 - Use tabs for indention. This is legacy and probably should be 2 or 4 spaces instead
 - Opening curly braces go on same line. Closing curly braces go on their own line. 
 - Look at existing code for more details

## Contributing

 - E-mail diffs to joe@u13.net
 - Pull requests on github - https://github.com/jrgp/linfo
