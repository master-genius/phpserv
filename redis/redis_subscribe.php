<?php
function sub_handle($rd, $chan, $msg)
{
    switch ($chan)  {
        case 'chan1':
            echo "chan1: $msg\n";
            break;
        case 'chan2':
            echo "chan2: $msg\n";
            break;
        case 'chan3':
            echo "chan3: $msg\n";
            break;
        default:;
    }
}

$rd = new Redis();
//$rd->connect('127.0.0.1',6379);
$rd->connect('127.0.0.1',6379,10,NULL,50);
$rd->subscribe(['chan1','chan2','chan3'], 'sub_handle');

