<?php
// PhpMqttPublish 0.0.1
// Desenvolvido por Silvio Garbes em 2022-07-28

// http://docs.oasis-open.org/mqtt/mqtt/v3.1.1/os/mqtt-v3.1.1-os.html#_Toc398718018

$server = 'broker.hivemq.com';
$port = 1883;
$client_id = 'PhpMqttPublish';

function connect($server, $port, $client_id){

    $socket = stream_socket_client('tcp://' . $server . ':' . $port, $errno, $errstr, 5);
    if (!$socket){
        echo "$errstr ($errno)";
        return false;
    }

    $buffer = '';
    # --------------- #
    # Variable header #
    # --------------- #
    // Protocol Name
    $buffer .= pack("c*", 
        0b00000000, // Length MSB (0)
        0b00000100, // Length LSB (4)
        0b01001101, // M
        0b01010001, // Q
        0b01010100, // T
        0b01010100  // T
    );

    // Protocol Level
    $buffer .= pack("c*", 
        0b00000100 // Level(4)
    );

    // Connect Flags
    $buffer .= pack("c*",
        0b00000010 // [User Name Flag] [Password Flag] [Will Retain] [Will QoS] [Will QoS] [Will Flag] [Clean Session] [Reserved]
    );

    // Keep Alive
    $buffer .= pack("c*",
        0b00000010, // Keep Alive MSB
        0b01011000  // Keep Alive LSB
    );

    # ------- #
    # Payload #
    # The payload of the CONNECT Packet contains one or more length-prefixed fields, whose presence is determined by the flags in the variable header.
    # These fields, if present, MUST appear in the order Client Identifier, Will Topic, Will Message, User Name, Password
    # ------- #

    // Client Identifier
    $buffer .= pack("c*", 
        (strlen($client_id) >> 8), // String length MSB
        (strlen($client_id) % 256) // String length LSB
    );
    $buffer .= $client_id; // String

    # ------------ #
    # Fixed header #
    # ------------ #
    // CONNECT Packet fixed header
    $head = pack("c*",
        0b00010000 // [MQTT Control Packet type (1)] [Reserved]
    );

    // Remaining Length
    $x = strlen($buffer);
    do
    {
        $encodedByte = $x % 128;
        $x = (int)($x / 128);
        if ($x > 0){
            $encodedByte = $encodedByte | 128;
        }
        $head .= chr($encodedByte);
    }
    while ($x > 0);

    // Send packages
    fwrite($socket, $head);
    fwrite($socket, $buffer);

    # ---------------------------------------- #
    # CONNACK – Acknowledge connection request #
    # ---------------------------------------- #
    $string = fread($socket, 4); // Fixed header (2 bytes) and Variable header (2 bytes)

    $binary = [];
    foreach (str_split($string) as $character){
        $data = unpack('H*', $character);
        $binary[] = base_convert($data[1], 16, 2);
    }

    if ($binary[0] != '100000'){ // MQTT Control Packet Type (Connect acknowledgment)
        return false;
    }

    if ($binary[3] != '0'){ // Connect Return code (Connection accepted)
        return false;
    }

    return $socket;
}

// PUBLISH – Publish message
function publish($socket, $topic, $content){

    $buffer = '';
    // Topic Name
    $buffer .= pack("c*",
        (strlen($topic) >> 8), // String length MSB
        (strlen($topic) % 256) // String length LSB
    );
    $buffer .= $topic; // String
    $buffer .= $content;

    // PUBLISH Packet fixed header
    $head = pack("c*",
        0b00110000 // [MQTT Control Packet type (3)] [DUP] [QoS level] [Retain]
    );

    // Remaining Length
    $x = strlen($buffer);
    do {
        $encodedByte = $x % 128;
        $x = (int)($x / 128);
        if ($x > 0){
            $encodedByte = $encodedByte | 128;
        }
        $head .= chr($encodedByte);
    } while ($x > 0);

    // Send packages
    fwrite($socket, $head, strlen($head));
    fwrite($socket, $buffer);
}

// DISCONNECT – Disconnect notification
function close($socket) {

    // DISCONNECT Packet fixed header
    $head = pack("c*",
        0b11100000, // [MQTT Control Packet type (14)]
        0b00000000  // Remaining Length (0)
    );

    fwrite($socket, $head, 2);
    stream_socket_shutdown($socket, STREAM_SHUT_WR);
}

# ------------------------------ #
# ------------------------------ #

$topic = 'PhpMqttPublish/publishtest';
$content = 'Hello World! at ' . date('r');
    
if ($socket = connect($server, $port, $client_id)) {
    publish($socket, $topic, $content);
    close($socket);
}else{
    echo "Time out!\n";
}

?>
