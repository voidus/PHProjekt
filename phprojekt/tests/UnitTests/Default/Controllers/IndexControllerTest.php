<?php
/**
 * This software is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License version 3 as published by the Free Software Foundation
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * @copyright  Copyright (c) 2010 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 * @version    Release: 6.1.0
 */


/**
 * Tests for Index Controller
 *
 * @version    Release: 6.1.0
 * @group      default
 * @group      controller
 * @group      default-controller
 */
class Phprojekt_IndexController_Test extends FrontInit
{
    protected function getDataSet() {
        return $this->createFlatXMLDataSet(dirname(__FILE__) . '/../data.xml');
    }
    /**
     * Test if the index page is displayed correctly
     */
    public function testIndexIndexAction()
    {
        $this->setRequestUrl('index/index');
        $response = $this->getResponse();
        $this->assertContains("PHProjekt", $response);
        $this->assertContains("<!-- template: index.phml -->", $response);
    }

    /**
     * Test if the list json response is ok
     */
    public function testJsonListAction()
    {
        $this->setRequestUrl('Project/index/jsonList/');
        $this->request->setParam('nodeId', null);

        try {
            $this->front->dispatch($this->request, $this->response);
        } catch (Zend_Controller_Action_Exception $error) {
            $this->assertEquals(IndexController::NODEID_REQUIRED_TEXT, $error->getMessage());
            return;
        }

        $this->fail('Error on Get the list');
    }

    /**
     * Test if the list json response is ok
     */
    public function testJsonListActionWithNodeId()
    {
        $this->setRequestUrl('Project/index/jsonList/');
        $this->request->setParam('nodeId', 1);
        $response = $this->getResponse();
        $this->assertContains('"numRows":2}', $response);
    }

    /**
     * Test if the list json response with  is ok
     */
    public function testJsonListActionWithNodeIdAndRecursive()
    {
        $this->setRequestUrl('Project/index/jsonList/');
        $this->request->setParam('nodeId', 1);
        $this->request->setParam('recursive', 'true');
        $response = $this->getResponse();
        $this->assertContains('"numRows":3}', $response);
    }

    /**
     * Test of json detail model
     */
    public function testJsonDetailAction()
    {
        $this->setRequestUrl('Project/index/jsonDetail/');
        $this->request->setParam('id', 1);
        $this->request->setParam('nodeId', 1);
        $response = $this->getResponse();
        $expected = '{"key":"title","label":"Title","originalLabel":"Title","type":"text","hint":"","listPosition":1,'
            . '"formPosition":1';
        $this->assertContains($expected, $response);
        $this->assertContains('"numRows":1}', $response);
    }

    /**
     * Test of json detail model
     */
    public function testJsonDetailActionWithoutId()
    {
        $this->setRequestUrl('Project/index/jsonDetail');
        $this->request->setParam('nodeId', 1);
        $response = $this->getResponse();
        $this->assertContains('"metadata":[{"key":"title"', $response);
    }

    /**
     * Test of json tree
     */
    public function testJsonTreeAction()
    {
        $this->setRequestUrl('Project/index/jsonTree');
        $response = $this->getResponse();
        $this->assertContains('"identifier":"id","label":"name","items":[{"name":"Invisible Root"', $response);
        $this->assertContains('"parent":"1","path":"\/1\/"}', $response);
    }

    /**
     * Test of json get submodules
     */
    public function testJsonGetModulesPermission()
    {
        $this->setRequestUrl('Project/index/jsonGetModulesPermission/');
        $this->request->setParam('nodeId', 1);
        $response = $this->getResponse();
        $this->assertContains('"name":"Project","label":"Project","inProject":true,"rights":{"none":false,', $response);
    }

    /**
     * Test of json get submodules -without a project Id-
     */
    public function testJsonGetModulesPermissionNoId()
    {
        $this->setRequestUrl('Project/index/jsonGetModulesPermission/');
        $this->request->setParam('nodeId', null);
        $response = $this->getResponse();
        $this->assertContains('{"metadata":[]}', $response);
    }

    /**
     * Test of json delete project -without a project Id-
     * @expectedException Zend_Controller_Action_Exception
     */
    public function testJsonDeleteNoId()
    {
        $this->setRequestUrl('Project/index/jsonDelete');
        $this->getResponse();
    }

    /**
     * Test the get all translated strings
     */
    public function testJsonGetTranslatedStrings()
    {
        $this->markTestSkipped('uhm');
        $this->setRequestUrl('Project/index/jsonGetTranslatedStrings');
        $response = $this->getResponse();
        $this->assertContains('ItemId":"Item', $response);
        $this->assertContains('Filter_equal_rule":"Equal', $response);
    }

    /**
     * Test of csv
     */
    public function testCsvListNodeId()
    {
        $this->setRequestUrl('Project/index/csvList/');
        $this->request->setParam('nodeId', '1');
        $response = $this->getResponse();
        $this->assertContains('"Title"'."\n"
            . '"Project 1"'."\n"
            . '"test"'."\n", $response);
    }

    /**
     * Test of csv
     */
    public function testCsvListId()
    {
        $this->setRequestUrl('Project/index/csvList/');
        $this->request->setParam('id', '1');
        $this->request->setParam('nodeId', 1);
        $response = $this->getResponse();
        $this->assertContains('"Title"'."\n"
            . '"Invisible Root"'."\n", $response);
    }

    /**
     * Test of csv
     */
    public function testCsvExportMultipleAction()
    {
        $this->setRequestUrl('Project/index/csvExportMultiple/');
        $this->request->setParam('ids', '1,2');
        $this->request->setParam('nodeId', 1);
        $response = $this->getResponse();
        $this->assertContains('"Title"'."\n"
            . '"Invisible Root"'."\n"
            . '"Project 1"' . "\n", $response);
    }

    /**
     * Test of JsonDeleteMultipleAction
     */
    public function testJsonDeleteMultipleActionPart1()
    {
        $this->setRequestUrl('Project/index/jsonList/');
        $this->request->setParam('nodeId', 1);
        $response = $this->getResponse();
        $this->assertContains('"numRows":2}', $response);
    }

    /**
     * Test of JsonDeleteMultipleAction
     */
    public function testJsonDeleteMultipleActionPart2()
    {
        $this->setRequestUrl('Project/index/jsonDeleteMultiple/');
        $this->request->setParam('ids', '2,3');
        $this->request->setParam('nodeId', 1);
        $response = $this->getResponse();
        $this->assertContains('The Items were deleted correctly', $response);
    }

    /**
     * Test of filters
     */
    public function testGetFilterWherePart1()
    {
        $this->setRequestUrl('Project/index/jsonList/');
        $this->request->setParam('nodeId', 1);
        $response = $this->getResponse();
        $this->assertContains('"numRows":2}', $response);
    }

    /**
     * Test of filters
     */
    public function testGetFilterWherePart2()
    {
        $this->setRequestUrl('Project/index/jsonList/');
        $this->request->setParam('nodeId', 1);
        $this->request->setParam('filters', '[["AND","title","like","test"]]');
        $response = $this->getResponse();
        $this->assertContains('"numRows":1}', $response);
    }

    /**
     * Test of jsonGetExtraActionsAction
     */
    public function testJsonGetExtraActionsAction()
    {
        $this->setRequestUrl('Project/index/jsonGetExtraActions');
        $response = $this->getResponse();
        $this->assertContains('[{"target":1,"action":"jsonDeleteMultiple","label":"Delete","mode":0,'
            . '"class":"deleteOption"},{"target":1,"action":"csvExportMultiple","label":"Export","mode":1,'
            . '"class":"exportOption"}]', $response);
    }

    /**
     * Test of jsonGetConfigurationsAction
     */
    public function testJsonGetConfigurationsAction()
    {
        $this->setRequestUrl('Project/index/jsonGetConfigurations');
        $response = Zend_Json::decode($this->getResponse());
        $expected = array(
            array(
                'name' => 'supportAddress',
                'value' => 'gustavo.solt@mayflower.de'
            ),
            array(
                'name' => 'currentUserId',
                'value' => 1
            )
        );
        foreach ($expected as $e){
            $this->assertContains($e, $response);
        }

        $hasCSRFToken = false;
        $hasVersion   = false;
        foreach ($response as $r){
            if ($r['name'] === 'csrfToken') {
                $hasCSRFToken = true;
            } else if ($r['name'] === 'phprojektVersion') {
                $hasVersion = true;
            }
        }
        $this->assertTrue($hasCSRFToken);
        $this->assertTrue($hasVersion);
    }

    /**
     * Test of jsonGetUsersRightsAction
     */
    public function testJsonGetUsersRightsAction()
    {
        $this->setRequestUrl('Project/index/jsonGetUsersRights/');
        $this->request->setParam('id', 2);
        $response = Zend_Json::decode($this->getResponse());
        $expected = array (
            1 => array (
                'none' => false,
                'read' => true,
                'write' => true,
                'access' => true,
                'create' => true,
                'copy' => true,
                'delete' => true,
                'download' => true,
                'admin' => true,
                'moduleId' => 1,
                'itemId' => 2,
                'userId' => 1,
            ),
        );
        $this->assertEquals($expected, $response);
    }
}
