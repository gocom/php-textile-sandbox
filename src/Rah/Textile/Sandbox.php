<?php

namespace Rah\Textile;
use Netcarver\Textile\Parser as Textile;

/**
 * Textile sandbox parser.
 */

class Sandbox
{
    /**
     * Parameters.
     *
     * @var array
     */

    protected $parameters = array(
        'method'   => 'restricted',
        'text'     => '',
        'doctype'  => 'html5',
        'lite'     => false,
        'noimage'  => true,
        'rel'      => 'nofollow',
        'callback' => '',
    );

    /**
     * Maximum Textile input length in bytes.
     *
     * @var int
     */

    protected $maxInputLength = 10000;

    /**
     * Input.
     *
     * @var array
     */

    protected $input = array();

    /**
     * Output.
     *
     * @var string
     */

    protected $output = '';

    /**
     * Cache directory.
     *
     * @var string/bool
     */

    public $cache = false;

    /**
     * Response body.
     *
     * @var string
     */

    protected $responseBody = '';

    /**
     * Request key.
     *
     * @var string
     */

    public $key = false;

    /**
     * Initializes.
     */

    static public function init(array $options = null)
    {
        $input = new Sandbox;

        foreach ((array) $options as $name => $value)
        {
            $input->$name = $value;
        }

        header('Access-Control-Allow-Origin: *');
        header('X-Robots-Tag: noindex');

        try
        {
            $input->getResponse();
            $input->sendResponse();
        }
        catch (\Exception $e)
        {
            header('HTTP/1.1 500 Internal Server Error');
            header('Status: 500 Internal Server Error');

            $input->sendResponse(array(
                'error' => array(
                    'message' => $e->getMessage(),
                )
            ));
        }
    }

    /**
     * Filter input value.
     */

    protected function filterInput()
    {
        if (!$this->input['text'])
        {
            throw new \Exception('No Textile input specified.');
        }

        if (strlen($this->input['text']) > $this->maxInputLength)
        {
            throw new \Exception('Maximum input length is '.$this->maxInputLength.' bytes.');
        }

        if (!in_array($this->input['doctype'], array('html5', 'xhtml')))
        {
            throw new \Exception('Invalid specified doctype.');
        }

        if (!in_array($this->input['method'], array('restricted', 'unrestricted')))
        {
            throw new \Exception('Invalid formatting method.');
        }

        if ($this->input['callback'])
        {
            $reserved = array('break', 'do', 'instanceof', 'typeof', 'case', 'else', 'new', 'var', 'catch', 'finally', 'return', 'void', 'continue', 'for', 'switch', 'while', 'debugger', 'function', 'this', 'with',  'default', 'if', 'throw', 'delete', 'in', 'try', 'class', 'enum',  'extends', 'super', 'const', 'export', 'import', 'implements', 'let', 'private', 'public', 'yield', 'interface', 'package', 'protected', 'static', 'null', 'true', 'false');

            foreach (explode('.', $this->input['callback']) as $part)
            {
                if (!preg_match('/^[$_\p{L}][$_\p{L}\p{Mn}\p{Mc}\p{Nd}\p{Pc}\x{200C}\x{200D}]*+$/u', $part) || in_array(strtolower($part), $reserved))
                {
                    throw new \Exception('Invalid JSON-P callback parameter.');
                }
            }
        }
    }

    /**
     * Restricted formatter.
     */

    protected function formatRestricted()
    {
        $textile = new Textile($this->input['doctype']);

        return $textile->textileRestricted(
            $this->input['text'],
            $this->input['lite'],
            $this->input['noimage'],
            $this->input['rel']
        );
    }

    /**
     * Un-restricted formatter.
     */

    protected function formatUnrestricted()
    {
        $textile = new Textile($this->input['doctype']);

        return $textile->textileThis(
            $this->input['text'],
            $this->input['lite'],
            false,
            $this->input['noimage'],
            false,
            $this->input['rel']
        );
    }

    /**
     * Gets the response body.
     *
     * @throws \Exception
     */

    protected function getResponse()
    {
        $this->input = $this->parameters;

        if ($this->key && (!isset($_REQUEST['key']) || $_REQUEST['key'] !== $this->key))
        {
            throw new \Exception('Access denied due to invalid key.');
        }

        foreach ($this->parameters as $name => $default)
        {
            $value = null;

            if (isset($_GET[$name]))
            {
                $value = $_GET[$name];
            }

            if (isset($_POST[$name]))
            {
                $value = $_POST[$name];
            }

            if ($value !== null)
            {
                if (is_bool($default))
                {
                    $this->input[$name] = (bool) $_GET[$name];
                }
                else if (is_int($default))
                {
                    $this->input[$name] = (int) $_GET[$name];
                }
                else
                {
                    $this->input[$name] = (string) $_GET[$name];
                }
            }
        }

        $this->filterInput();

        if ($this->cache && $cacheId = md5(json_encode($this->input)))
        {
            if (file_exists($this->cache . '/' . $cacheId))
            {
                $this->responseBody = file_get_contents($this->cache . '/' . $cacheId . '.json');
                return;
            }
        }

        $method = 'format'.ucfirst($this->input['method']);
        $this->output = $this->$method();
        $this->responseBody = (string) json_encode(array(
            'options' => $this->input,
            'output'  => $this->output,
        ));

        if ($this->cache && $cacheId)
        {
            file_put_contents($this->cache . '/' . $cacheId, $this->responseBody);
        }
    }

    /**
     * Encodes and sends a JSON or JSON-P response.
     *
     * @param  array $input Array to encode and send
     * @throws \Exception
     */

    protected function sendResponse(array $input = null)
    {
        if ($input === null && $this->responseBody)
        {
            $json = $this->responseBody;
        }
        else
        {
            $json = (string) json_encode($input);
        }

        if ($this->input['callback'])
        {
            header('Content-Type: text/javascript; charset=utf-8');
            echo $this->input['callback'] . '(' . $json . ')';
        }
        else
        {
            header('Content-Type: application/json; charset=utf-8');
            echo $json;
        }
    }
}
