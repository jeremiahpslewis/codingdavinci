<?php

namespace Service\Gender;


class MergingChainProvider implements GenderProviderInterface
{
    /**
     * @var GenderProviderInterface[]
     */
    private $providers = array();

    /**
     * Constructor
     *
     * @param GenderProviderInterface[] $providers
     */
    public function __construct(array $providers = array())
    {
        $this->providers = $providers;
    }

    /**
     * Add a provider
     *
     * @param GenderProviderInterface $provider
     */
    public function addProvider(GenderProviderInterface $provider)
    {
        $this->providers[] = $provider;
    }

    protected function checkSingle($result) {
        if (!isset($result->probability) || $result->probability < 0.75) {
            return false;
        }
        return true;
    }

    protected function buildAggregateResult($name, &$processed, &$todo) {
        if (is_array($name)) {
            $results = array();
            foreach ($name as $single_name) {
                $results[] = isset($processed[$single_name])
                    ? $processed[$single_name] : $todo[$single_name];
            }
            return $results;
        }
        return isset($processed[$name]) ? $processed[$name] : $todo[$name];
    }

    protected function compareSingle($result_a, $result_b) {
        if (empty($result_a)) {
            return $result_b;
        }
        if (empty($result_b)) {
            return $result_a;
        }
        if (!isset($result_a->probability)) {
            return $result_b;
        }
        if (!isset($result_b->probability)) {
            return $result_a;
        }

        return $result_b->probability > $result_a->probability
            ? $result_b : $result_a;
    }

    /**
     * {@inheritDoc}
     */
    public function guess($name, $country_id = null, $language_id = null)
    {
        if (empty($name)) {
            return;
        }

        $exceptions = array();

        $processed = array();
        $todo = array();
        $multiple = false;
        if (is_array($name)) {
            $multiple = true;
            foreach ($name as $single_name) {
                $todo[$single_name] = array();
            }
        }
        else {
            $todo[$name] = array();
        }

        foreach ($this->providers as $provider) {
            try {
                $names = array_keys($todo);
                if (count($names) == 1) {
                    $names = $names[0];
                }
                $results = $provider->guess($names, $country_id, $language_id);

                if (isset($results)) {
                    if (is_array($names)) {
                        for ($i = 0; $i < count($names); $i++) {
                            $ok = $this->checkSingle($results[$i]);
                            if ($ok) {
                                $processed[$name[$i]] = $results[$i];
                                unset($todo[$name[$i]]);
                            }
                            else {
                                $todo[$names[$i]] = $this->compareSingle($results[$i], $todo[$names[$i]]);
                            }
                        }
                        if (empty($todo)) {
                            return $this->buildAggregateResult($name, $processed, $todo);
                        }
                    }
                    else {
                        if ($this->checkSingle($results)) {
                            return $multiple ? array($results) : $results;
                        }
                        $todo[$names] = $this->compareSingle($results, $todo[$names]);
                    }
                }
            }
            catch (\Exception $e) {
                $exceptions[] = $e;
            }
        }

        return $this->buildAggregateResult($name, $processed, $todo);
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'gender-chain';
    }
}
