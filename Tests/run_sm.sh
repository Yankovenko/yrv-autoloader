#!/bin/bash
#dir=$(cd "$(dirname "$0")";pwd);

# Capture the current directory
current_dir=$(pwd)

# Full path to the config file
configfile=$(realpath "${current_dir}/phpunit_sm.xml")

# Print the full path
echo "$configfile"

#cd $dir
php ../../../vendor/phpunit/phpunit/phpunit -c $configfile $* .

##
