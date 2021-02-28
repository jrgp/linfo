#!/bin/bash

# Joe Gillotti - 7/19/14 - MIT
# Compile sass templates to css using http://sass-lang.com/

style=compressed

for file in theme_*.sass; do
  sass  --style $style --no-source-map  $file ../${file%.sass}.css
done

# Compile mobile sass to css
sass  --style $style --no-source-map mobile.sass ../mobile.css
# Compile icon sass to css
sass  --style $style --no-source-map icons.sass ../icons.css
