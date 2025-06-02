#!/bin/sh

# Add /usr/static-php/bin to PATH if it's not already there
if ! echo "$PATH" | grep -q "/usr/static-php/bin"; then
    export PATH="$PATH:/usr/static-php/bin"
fi
