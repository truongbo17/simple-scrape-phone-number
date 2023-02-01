<?php

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

final class ScrapePhone
{
    private string $url;

    private string $driver;

    private string $type_dom;

    private string $dom;

    private string $should_get_info_name = "";

    private string $should_get_info_phone = "";

    private string $should_get_info = "";

    private array $guzzle_config = [
        'timeout' => 1000,
        'verify' => false,
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.106 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
        ],
        'http_errors' => false,
        'allow_redirects' => [
            'track_redirects' => true
        ],
    ];

    private string $ext_save_file = "json";

    public function __construct()
    {
        $this->input();
    }

    private function input(): void
    {
        $this->url = readline("Nhập url cần scrape phone : ");
        $this->url = trim($this->url);
        if (!filter_var($this->url, FILTER_VALIDATE_URL)) {
            die("Vui lòng nhập đúng định dạng url ! \n");
        }

        $drivers = implode(",", ["guzzle"]);
        $this->driver = readline("Chọn driver \"{$drivers}\" (default using guzzle) : ");
        if (empty($this->driver)) $this->driver = "guzzle";
        $this->driver = strtolower($this->driver);
        $this->driver = trim($this->driver);
        if (!in_array($this->driver, ["guzzle"])) {
            die("Vui lòng chọn đúng driver ! \n");
        }

        $types = implode(",", ["css", "xpath"]);
        $this->type_dom = readline("Chọn type dom \"{$types}\" (default using css) : ");
        if (empty($this->type_dom)) $this->type_dom = "css";
        $this->type_dom = strtolower($this->type_dom);
        $this->type_dom = trim($this->type_dom);
        if (!in_array($this->type_dom, ["css", "xpath"])) {
            die("Vui lòng chọn đúng type dom ! \n");
        }

        $doms = implode(",", ["table", "string"]);
        $this->dom = readline("Chọn type dom \"{$doms}\" (default using string) : ");
        if (empty($this->dom)) $this->dom = "string";
        $this->dom = strtolower($this->dom);
        $this->dom = trim($this->dom);
        if (!in_array($this->dom, ["table", "string"])) {
            die("Vui lòng chọn đúng dom ! \n");
        }

        if ($this->dom == "string") {
            $this->should_get_info = readline("Nhập dom cần lấy dữ liệu : ");
            if (empty($this->should_get_info)) {
                die("Vui lòng nhập dom ! \n");
            }
        } elseif ($this->dom == "table") {
            $this->should_get_info = readline("Nhập dom của bảng cần lấy dữ liệu : ");
            if (empty($this->should_get_info)) {
                die("Vui lòng nhập dom của bảng (thẻ tr) ! \n");
            }
            $this->should_get_info_name = readline("Nhập dom cần lấy dữ liệu của Tên trong bảng : ");
            if (empty($this->should_get_info_name)) {
                die("Vui lòng nhập dom của Tên ! \n");
            }
            $this->should_get_info_phone = readline("Nhập dom cần lấy dữ liệu của Số điện thoại trong bảng : ");
            if (empty($this->should_get_info_phone)) {
                die("Vui lòng nhập dom của Số điện thoại ! \n");
            }
        }
    }

    public function scrape()
    {
        try {
            $html = $this->getHtml();
            $dom_crawler = new Crawler($html);

            $result = [];
            if ($this->dom == "string") {
                //crawl with string
                if ($this->type_dom == "css") {
                    $dom_crawler = $dom_crawler->filter($this->should_get_info);
                } else if ($this->type_dom == "xpath") {
                    $dom_crawler = $dom_crawler->filterXPath($this->should_get_info);
                }
                $result = $dom_crawler->each(function ($node, $i) {
                    return $node->text();
                });
            } elseif ($this->dom == "table") {
                //crawl with table
                if ($this->type_dom == "css") {
                    $result = $dom_crawler->filter($this->should_get_info)->each(function ($node, $i) {
                        return [
                            $node->filter($this->should_get_info_name)->text(),
                            $node->filter($this->should_get_info_phone)->text(),
                        ];
                    });
                } else if ($this->type_dom == "xpath") {
                    $result = $dom_crawler->filterXPath($this->should_get_info)->each(function ($node, $i) {
                        return [
                            $node->filterXPath($this->should_get_info_name)->text(),
                            $node->filterXPath($this->should_get_info_phone)->text(),
                        ];
                    });
                }
            }

            $file_name = __DIR__ . DIRECTORY_SEPARATOR . "output" . DIRECTORY_SEPARATOR . $this->slugify($this->url) . "_" . $this->randName(10) . ".{$this->ext_save_file}";
            file_put_contents($file_name, json_encode($result));

            die("\033[32m Lấy dữ liệu thành công ! Vui lòng kiểm tra file {$file_name} \n");
        } catch (Exception $exception) {
            die("{$exception->getMessage()} \n");
        }
    }

    private function getHtml(): string
    {
        return match ($this->driver) {
            'guzzle' => $this->guzzle(),
        };
    }

    private function slugify($text): string
    {// replace non letter or digits by divider
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        // trim
        $text = trim($text, '-');

        // remove duplicate divider
        $text = preg_replace('~-+~', '-', $text);

        // lowercase
        $text = strtolower($text);

        if (empty($text)) {
            return 'n-a';
        }

        return $text;
    }

    private function randName(int $number): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $random_string = '';

        for ($i = 0; $i < $number; $i++) {
            $index = rand(0, strlen($characters) - 1);
            $random_string .= $characters[$index];
        }

        return $random_string;
    }

    private function guzzle(): string
    {
        $client = new Client($this->guzzle_config);
        $response = $client->get($this->url);
        $html = $response->getBody()->getContents();
        if (mb_stripos($html, "</a>") === false && mb_stripos($html, "<body") === false) {
            $html = mb_convert_encoding($html, "UTF-8", "UTF-16LE");
        } elseif (mb_stripos($html, "charset=Shift_JIS")) {
            $html = mb_convert_encoding($html, "UTF-8", "SJIS");
            $html = str_replace("charset=Shift_JIS", "charset=UTF-8", $html);
        }
        return (string)$html;
    }
}

$scrape = new ScrapePhone();
$scrape->scrape();