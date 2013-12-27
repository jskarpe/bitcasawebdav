#!/bin/bash
MYFILE=$1
RESPONSE=$(curl -v -H "Pragma: no-cache" -H "Cache-Control: no-cache" --header "Range: bytes=0-2" $MYFILE)

echo "$MYFILE"
echo "$RESPONSE"
