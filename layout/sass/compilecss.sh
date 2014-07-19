#!/bin/bash

# Joe Gillotti - 7/19/14 - GPL
# Compile sass templates to css using http://sass-lang.com/

for file in theme_*.sass; do
  sass --unix-newlines $file ../${file%.sass}.css
done

