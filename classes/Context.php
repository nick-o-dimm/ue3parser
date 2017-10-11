<?php

/**
 * Class Context
 */
class Context
{

    /** @var array */
    private $data = array();

    /**
     * Context constructor.
     * @param array $data
     */
    public function __construct($data = array())
    {
        if (is_array($data)) {
            $this->addData($data);
        }
    }

    /**
     * Get item value
     * @param $keyName
     * @return mixed|null
     */
    public function get($keyName)
    {
        return $this->data[$keyName] ?? NULL;
    }

    /**
     * Set item value. Auto replace {$varName} sequences with values.
     * @param $keyName
     * @param $value
     */
    public function set($keyName, $value)
    {
        if (is_string($value)) {
            $self = $this;

            $value = preg_replace_callback('#{\$(.*?)}#',
                function ($matches) use ($self) {
                    $newValue = $self->get($matches[1]);
                    return (!is_null($newValue)) ? $newValue : $matches[0];
                },
                $value
            );
        }

        $this->data[$keyName] = $value;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param array $data
     */
    public function addData($data)
    {
        foreach ($data as $keyName => $value) {
            $this->set($keyName, $value);
        }
    }

    /**
     * @param Context $context
     */
    public function addContext(Context $context)
    {
        $newData = $context->getData();
        $this->addData($newData);
    }

}
