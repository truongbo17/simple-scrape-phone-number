<?php

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use JetBrains\PhpStorm\NoReturn;
use Symfony\Component\DomCrawler\Crawler;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

    private string $explode_separator = ":";

    private string $template_file_path = "./template/import_contact.xlsx";

    private string $file_name_output = "";

    private array $urls_scraped = [];

    public function __construct(bool $validate_url_scraped = true)
    {
        $this->input($validate_url_scraped);
    }

    private function input(bool $validate_url_scraped): void
    {
        $this->url = readline("Nhập url cần scrape phone : ");
        $this->url = trim($this->url);
        if (!filter_var($this->url, FILTER_VALIDATE_URL)) {
            die("Vui lòng nhập đúng định dạng url ! \n");
        }
        if ($validate_url_scraped) {
            $this->checkUrlScraped($this->url);
        }

//        $drivers = implode(",", ["guzzle"]);
//        $this->driver = readline("Chọn driver \"{$drivers}\" (default using guzzle) : ");
//        if (empty($this->driver)) $this->driver = "guzzle";
//        $this->driver = strtolower($this->driver);
//        $this->driver = trim($this->driver);
//        if (!in_array($this->driver, ["guzzle"])) {
//            die("Vui lòng chọn đúng driver ! \n");
//        }
        $this->driver = "guzzle";

        $types = implode(",", ["css", "xpath"]);
        $this->type_dom = readline("Chọn type dom \"{$types}\" (default using css) : ");
        if (empty($this->type_dom)) $this->type_dom = "css";
        $this->type_dom = strtolower($this->type_dom);
        $this->type_dom = trim($this->type_dom);
        if (!in_array($this->type_dom, ["css", "xpath"])) {
            die("Vui lòng chọn đúng type dom ! \n");
        }

        $doms = implode(",", ["table", "string", "custom"]);
        $this->dom = readline("Chọn type dom \"{$doms}\" (default using string) : ");
        if (empty($this->dom)) $this->dom = "string";
        $this->dom = strtolower($this->dom);
        $this->dom = trim($this->dom);
        if (!in_array($this->dom, ["table", "string", "custom"])) {
            die("Vui lòng chọn đúng dom ! \n");
        }

        if ($this->dom == "string") {
            $this->should_get_info = readline("Nhập dom cần lấy dữ liệu : ");
            if (empty($this->should_get_info)) {
                die("Vui lòng nhập dom ! \n");
            }
            $explode_separator = readline("Phân tách giữa tên và số điện thoại bằng kí tự nào (default ':' ) : ");
            if (!empty($explode_separator)) $this->explode_separator = $explode_separator;
        } elseif ($this->dom == "table") {
            $this->should_get_info = readline("Nhập dom của bảng cần lấy dữ liệu (thẻ tr) : ");
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
        } elseif ($this->dom == "custom") {
            $this->should_get_info = readline("Nhập dom của bảng cần lấy dữ liệu (thẻ tr) : ");
            if (empty($this->should_get_info)) {
                die("Vui lòng nhập dom của bảng (thẻ tr) ! \n");
            }
            $this->should_get_info_name = readline("Nhập dom cần lấy dữ liệu của Tên : ");
            if (empty($this->should_get_info_name)) {
                die("Vui lòng nhập dom của Tên ! \n");
            }
            $this->should_get_info_phone = readline("Nhập dom cần lấy dữ liệu của Số điện thoại : ");
            if (empty($this->should_get_info_phone)) {
                die("Vui lòng nhập dom của Số điện thoại ! \n");
            }
        }

        $this->file_name_output = readline("Nhập Tên file : ");
    }

    private function checkUrlScraped($url): void
    {
        try {
            $this->urls_scraped = json_decode(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'check_url_scraped.json'), true) ?? [];
            if (in_array($url, $this->urls_scraped)) {
                echo("Url đã được scrape ! \n");
            }
        } catch (Exception $exception) {
            echo $exception->getMessage();
        }
    }

    public function scrape(): string
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
                        if (
                            $node->filter($this->should_get_info_name)->count() > 0 &&
                            $node->filter($this->should_get_info_phone)->count() > 0
                        ) {
                            return [
                                $node->filter($this->should_get_info_name)->text(),
                                $node->filter($this->should_get_info_phone)->text(),
                            ];
                        }
                    });
                } else if ($this->type_dom == "xpath") {
                    $result = $dom_crawler->filterXPath($this->should_get_info)->each(function ($node, $i) {
                        if (
                            $node->filterXPath($this->should_get_info_name)->count() > 0 &&
                            $node->filterXPath($this->should_get_info_phone)->count() > 0
                        ) {
                            return [
                                $node->filterXPath($this->should_get_info_name)->text(),
                                $node->filterXPath($this->should_get_info_phone)->text(),
                            ];
                        }
                    });
                }
            }elseif ($this->dom == "custom") {
                //crawl with custom
                if ($this->type_dom == "css") {
                    $result = $dom_crawler->filter($this->should_get_info_name)->each(function ($node, $i) {

                    });
                } else if ($this->type_dom == "xpath") {
                    $result = $dom_crawler->filterXPath($this->should_get_info)->each(function ($node, $i) {

                    });
                }
            }
            echo "Lấy dữ liệu thành công ! \n";
            return $this->handleResult($result);
        } catch (Exception $exception) {
            die("{$exception->getMessage()} \n");
        }
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function handleResult($result): string
    {
        $spreadsheet = IOFactory::load($this->template_file_path);
        $worksheet = $spreadsheet->getActiveSheet();

        if (empty($this->file_name_output)) {
            $file_name = __DIR__ . DIRECTORY_SEPARATOR . "output" . DIRECTORY_SEPARATOR . $this->slugify($this->url) . "_" . $this->randName(10) . ".xls";
        } else {
            $file_name = __DIR__ . DIRECTORY_SEPARATOR . "output" . DIRECTORY_SEPARATOR . $this->file_name_output . "_" . $this->randName(10) . ".xls";
        }

        $start_cell = 2;

        if ($this->dom == "string") {
            foreach ($result as $value) {
                if (!empty($value)) {
                    $value = explode($this->explode_separator ?? " ", $value);
                    if (isset($value[0]) && isset($value[1])) {
                        $this->in($worksheet, $start_cell, $value);
                    }
                }
            }
        } else if ($this->dom == "table") {
            foreach ($result as $value) {
                if (!empty($value[0]) && !empty($value[1]) && $value[0] != "" && $value[1] != "") {
                    $this->in($worksheet, $start_cell, $value);
                }
            }
        }
        $writer = IOFactory::createWriter($spreadsheet, 'Xls');
        $writer->save($file_name);
        echo("Export ra file Excel thành công ! Vui lòng kiểm tra file {$file_name} \n");

        $this->urls_scraped[] = $this->url;
        file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . 'check_url_scraped.json', json_encode($this->urls_scraped));
        return $file_name;
    }

    private function in($worksheet, &$start_cell, $value): void
    {
        $worksheet->getCell("B{$start_cell}")->setValue($this->nameClear($value[0]));

        $value[1] = str_replace(["–", ",", "\r\n", "\n"], "-", $value[1]);
        $phones = explode("-", $value[1]);
        if (isset($phones[1])) {
            $worksheet->getCell("C{$start_cell}")->setValue($this->phoneClear($phones[0]));
            $worksheet->getCell("D{$start_cell}")->setValue($this->phoneClear($phones[1]));
        } else {
            $worksheet->getCell("C{$start_cell}")->setValue($this->phoneClear($value[1]));
        }

        $start_cell++;
    }

    private function phoneClear($phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }

    private function nameClear($name): string
    {
        return str_replace([",", ".", "&nbsp;", "\n", "\r\n"], "", trim($name));
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

$scrape = new ScrapePhone(true);
$scrape->scrape();