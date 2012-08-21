#!/bin/sh

for i in 16 24 32 48 64 128; do
	padding=$(($i+15))
	sed "s/####/$padding/" show_gravatar.css > show_gravatar_$i.css
done

