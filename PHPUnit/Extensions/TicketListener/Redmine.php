<?php
/**
 * PHPUnit
 *
 * Copyright (c) 2012-2013, MiRacLe <miracle@rpz.name>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Sebastian Bergmann nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   Testing
 * @package    PHPUnit
 * @subpackage Extensions_TicketListener
 * @author     MiRacLe.RPZ <miracle@rpz.name>
 * @license    http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 * @version    Release: @package_version@
 */
if (!defined('PHPUNIT_COMPOSER_INSTALL')) {
    require_once('PHPUnit/Extensions/TicketListener.php');
}


class PHPUnit_Extensions_TicketListener_Redmine extends PHPUnit_Extensions_TicketListener {
    private $url;
    private $apikey;
    private $curl;
    private $test;
    private $closedStatuses = array(5);
    private $openStatusId = array(1,2);
    private $reopenStatusId = 2;
    private $printTicketStateChanges;

    /**
     * @param string $url Redmine location
     * @param string $apikey Redmine API key
     * @param array $closedStatuses ids of issue's closed statuses
     * @param integer $openStatusId
     * @param integer $reopenStatusId
     * @param bool $printTicketStateChanges to display changes or not
     */
    public function __construct($url, $apikey, $closedStatuses = null, $openStatusId = null, $reopenStatusId = null, $printTicketStateChanges = false) {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('The dependent curl extension is not available');
        }
        $this->url = $url;
        $this->apikey = $apikey;
        if (!empty($closedStatuses) && is_array($closedStatuses)) {
            $this->closedStatuses = $closedStatuses;
        }
        if (!empty($openStatusId)) {
            if (!is_array($openStatusId)) {
                $openStatusId = array($openStatusId);
            }
            $this->openStatusId = $openStatusId;
        }
        if (!empty($reopenStatusId)) {
            $this->reopenStatusId = ($tmp = intval($reopenStatusId)) ? $tmp : $this->reopenStatusId;
        }
        $this->printTicketStateChanges = (bool)$printTicketStateChanges;
    }

    /**
     * getTicketInfo function.
     *
     * @param integer $issueId
     * @return array
     */
    public function getTicketInfo($issueId = null) {
        if (!ctype_digit($issueId)) {
            $status = 'invalid_ticket_id';
        } else {
            $issue = $this->runRequest('/issues/' . $issueId . '.xml');
            if ($issue) {
                $status_id = (int)$issue->status['id'];
                if (in_array($status_id, $this->closedStatuses)) {
                    $status = 'closed';
                } elseif (in_array($status_id, $this->openStatusId)) {
                    $status = 'new';
                } else {
                    $status = (string)$issue->status['name'];
                }
            } else {
                $status = 'not found';
            }
        }
        return array('status' => $status);
    }
    /**
     * @param integer $issueId
     * @param string $status
     * @param string $note
     * @param string $resolution
     */
    protected function updateTicket($issueId, $status, $note, $resolution) {
        $statusId = ($status == 'closed') ? reset($this->closedStatuses) : (($status == 'reopened') ? $this->reopenStatusId : null);
        if ($statusId) {
            $issue = $this->runRequest('/issues/' . $issueId . '.xml');
            if ($issue) {
                $status_id = (int)$issue->status['id'];
                if ($statusId != $status_id) {
                    $xml = new SimpleXMLElement('<?xml version="1.0"?><issue></issue>');
                    $xml->addChild('id', $issueId);
                    $xml->addChild('status_id', $statusId);
                    if (in_array($statusId,$this->openStatusId)) {
                        $note .= PHP_EOL.'Failed test: '.$this->test->toString().PHP_EOL.'Message: '.$this->test->getStatusMessage();
                    }
                    $xml->addChild('notes', htmlentities($note, ENT_COMPAT, 'UTF-8', false));
                    $this->runRequest('/issues/' . $issueId . '.xml', 'PUT', $xml->asXML());
                    if ($this->printTicketStateChanges) {
                        printf("\nUpdating Redmine issue #%d, status: %s\n", $issueId, $status);
                    }
                } else {
                    if ($this->printTicketStateChanges) {
                        printf("\nRedmine issue #%d, already has status: %s\n", $issueId, $status);
                    }
                }
            } else {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * @param string $restUrl
     * @param string $method
     * @param string $data
     */
    private function runRequest($restUrl, $method = 'GET', $data = '') {
        $method = strtolower($method);

        $this->curl = curl_init();

        // Authentication
        if (isset($this->apikey)) {
            curl_setopt($this->curl, CURLOPT_USERPWD, $this->apikey . ":" . rand(100000, 199999));
            curl_setopt($this->curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        }

        // Request
        switch ($method) {
            case "post":
                curl_setopt($this->curl, CURLOPT_POST, 1);
                if (isset($data)) curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data);
                break;
            case "put":
                curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'PUT');
                if (isset($data)) curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data);
                break;
            case "delete":
                curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, "DELETE");
                break;
            default: // get
                break;
        }

        // Run the request
        try {
            curl_setopt($this->curl, CURLOPT_URL, $this->url . $restUrl);
            curl_setopt($this->curl, CURLOPT_PORT, 80);
            curl_setopt($this->curl, CURLOPT_VERBOSE, 0);
            curl_setopt($this->curl, CURLOPT_HEADER, 0);
            curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($this->curl, CURLOPT_HTTPHEADER, array("Content-Type: text/xml", "Content-length: " . strlen($data)));

            $response = curl_exec($this->curl);
            if (!curl_errno($this->curl)) {
                $info = curl_getinfo($this->curl);
            } else {
                curl_close($this->curl);
                return false;
            }

            curl_close($this->curl);
        } catch (Exception $e) {
            //echo 'Exception: ',  $e->getMessage(), "\n";
            return false;
        }

        if ($response) {
            if (substr($response, 0, 1) == '<') {
                return new SimpleXMLElement($response);
            } else {
                return false;
            }
        }
        return true;
    }
    /**
     * A test ended.
     *
     * @param  PHPUnit_Framework_Test $test
     * @param  float                  $time
     */
    public function endTest(PHPUnit_Framework_Test $test, $time)
    {
        if (!$test instanceof PHPUnit_Framework_Warning) {
            if ($test->getStatus() == PHPUnit_Runner_BaseTestRunner::STATUS_PASSED) {
                $ifStatus   = array('assigned', 'new', 'reopened');
                $newStatus  = 'closed';
                $message    = 'Automatically closed by PHPUnit (test passed).';
                $resolution = 'fixed';
                $cumulative = TRUE;
            }

            else if ($test->getStatus() == PHPUnit_Runner_BaseTestRunner::STATUS_FAILURE) {
                $ifStatus   = array('closed');
                $newStatus  = 'reopened';
                $message    = 'Automatically reopened by PHPUnit (test failed).';
                $resolution = '';
                $cumulative = FALSE;
            }

            else {
                return;
            }

            $name    = $test->getName();
            $tickets = PHPUnit_Util_Test::getTickets(get_class($test), $name);

            foreach ($tickets as $ticket) {
                // Remove this test from the totals (if it passed).
                if ($test->getStatus() == PHPUnit_Runner_BaseTestRunner::STATUS_PASSED) {
                    unset($this->ticketCounts[$ticket][$name]);
                }

                // Only close tickets if ALL referenced cases pass
                // but reopen tickets if a single test fails.
                if ($cumulative) {
                    // Determine number of to-pass tests:
                    if (count($this->ticketCounts[$ticket]) > 0) {
                        // There exist remaining test cases with this reference.
                        $adjustTicket = FALSE;
                    } else {
                        // No remaining tickets, go ahead and adjust.
                        $adjustTicket = TRUE;
                    }
                } else {
                    $adjustTicket = TRUE;
                }

                $ticketInfo = $this->getTicketInfo($ticket);

                if ($adjustTicket && in_array($ticketInfo['status'], $ifStatus)) {
                    $this->test = $test;
                    $this->updateTicket($ticket, $newStatus, $message, $resolution);
                    $this->test = null;
                }
            }
        }
    }


}