<?php

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/vendor/autoload.php';

$dotenv = new \Dotenv\Dotenv(BASE_PATH);
$dotenv->load();

$log = new \Monolog\Logger('log');
$log->pushHandler(new \Monolog\Handler\StreamHandler(BASE_PATH . '/logger.log'));

set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($log) {
    if (0 === error_reporting()) {
        return false;
    }

    if ($log) {
        $log->warning("{$errstr} on {$errfile}:{$errline}");
    }

    echo "{$errstr} on {$errfile}:{$errline}" . PHP_EOL;

    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});

set_exception_handler(function (\Throwable $exception) use ($log) {
    $reflectedClass = new \ReflectionClass($exception);

    $errorCode = $exception->errorCode ?? $exception->getCode();

    $message = sprintf(
        'Exception %s (code %s): "%s" on %s:%s',
        $reflectedClass->getShortName(),
        $errorCode,
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine()
    );

    if ($log) {
        $log->error($message);
    }

    echo $message . PHP_EOL;
});

$loop = React\EventLoop\Factory::create();

$client = new Slack\RealTimeClient($loop);
$client->setToken(getenv('SLACK_TOKEN'));
$client->connect()->then(function () use ($client, $log) {
    $log->debug('Trying to connect to ' . getenv('CHANNEL_ID'));
    $client->getChannelGroupOrDMByID(getenv('CHANNEL_ID'))->then(function ($channel) use ($client, $log) {
        try {
            $log->debug('Obtaining xkcd comic');
            $lastComic = json_decode(file_get_contents("https://xkcd.com/info.0.json"), true);
            $ourLastComic = json_decode(file_get_contents(BASE_PATH . '/last.json'), true);

            $log->debug($lastComic['num'] . ' > ' . $ourLastComic['xkcd-last']);
            if ($lastComic['num'] > $ourLastComic['xkcd-last']) {
                $log->debug('New xkcd comic');
                $message = $client->getMessageBuilder()
                    ->setChannel($channel)
                    ->setText('[XKCD] ' . $lastComic['title'] . ' - https://xkcd.com/' . $lastComic['num'])
                    ->create();

                $log->debug('Message created');
                $client->postMessage($message);
                $log->debug('Message sent');

                $ourLastComic['xkcd-last'] = $lastComic['num'];
                file_put_contents(BASE_PATH . '/last.json', json_encode($ourLastComic));
            }

            $log->debug('Obtaining htz comic');
            $htzFeed = new SimpleXMLElement(file_get_contents('http://www.htzcomic.com/feed/'));
            $htzLastComic = $htzFeed->channel->item[0];
            preg_match('/http:\/\/www\.htzcomic\.com\/\?p=([0-9]+)/', $htzLastComic->guid, $htzMatches);
            $htzLastId = (int) $htzMatches[1];
            $log->debug($htzLastId . ' > ' . $ourLastComic['htz-last']);
            if (isset($htzLastId) && $htzLastId > $ourLastComic['htz-last']) {
                $log->debug('New htz comic');
                $img = (string) (new SimpleXMLElement((string) $htzLastComic->description))->a->img['src'];

                $att = new \Slack\Message\AttachmentBuilder();
                $message = $client->getMessageBuilder()
                    ->setChannel($channel)
                    ->setText('')
                    ->addAttachment((new \Slack\Message\AttachmentBuilder())
                            ->setTitle('[HTZ] ' . $htzLastComic->title, $htzLastComic->link)
                            ->setText('')
                            ->setImageUrl($img)
                            ->setThumbUrl($img)
                            ->create()
                    )
                    ->create();

                $log->debug('Message created');
                $client->postMessage($message);
                $log->debug('Message sent');

                /*$ourLastComic['htz-last'] = $htzLastId;
            file_put_contents(BASE_PATH . '/last.json', json_encode($ourLastComic));*/
            }
        } catch (\Exception $e) {
            $log->error('Error obtaining comic');
            $log->error($e);
        }

        $client->disconnect();
    }, function ($exception) use ($client, $log) {
        $log->error($exception);
        $client->disconnect();
    });
}, function ($exception) use ($client, $log) {
    $log->error($exception);
    $client->disconnect();
});

$log->info('Bot initialized');
$loop->run();
