#!/bin/bash

echo "Run this from the root of the project to build it for deployment"

#read in args: namespace and dir_name
namespace=$1
dir_name=$2

if [ -z "$namespace" ] || [ -z "$dir_name" ]; then
    echo "Usage: $0 <namespace> <dir_name>"
    exit 1
fi

#php-scope the whole project
php-scoper add-prefix --prefix="$namespace" --output-dir="$dir_name"

#copy the .distignore file to the new directory
cp .distignore "$dir_name/.distignore"

#dump the composer autoloader in the new directory
cd "$dir_name" && composer dump-autoload

#use wp dist-archive to make a zip file of the new directory
cd .. && wp dist-archive "$dir_name"