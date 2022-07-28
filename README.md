# PhpMqttPublish
A simple php script for connect to mqtt broker and publish

Reference: http://docs.oasis-open.org/mqtt/mqtt/v3.1.1/os/mqtt-v3.1.1-os.html#_Toc398718018

Example:

$server = 'broker.hivemq.com';
$port = 1883;
$client_id = 'PhpMqttPublish';

$topic = 'PhpMqttPublish/publishtest';
$content = 'Hello World! at ' . date('r');
    
if ($socket = connect($server, $port, $client_id)) {
    publish($socket, $topic, $content);
    close($socket);
}else{
    echo "Time out!\n";
}
