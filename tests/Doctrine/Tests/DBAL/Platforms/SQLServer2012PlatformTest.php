<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Doctrine\DBAL\Schema\Sequence;

class SQLServer2012PlatformTest extends AbstractSQLServerPlatformTestCase
{
    public function createPlatform()
    {
        return new SQLServer2012Platform;
    }

    public function testSupportsSequences()
    {
        $this->assertTrue($this->_platform->supportsSequences());
    }

    public function testDoesNotPreferSequences()
    {
        $this->assertFalse($this->_platform->prefersSequences());
    }

    public function testGeneratesSequenceSqlCommands()
    {
        $sequence = new Sequence('myseq', 20, 1);
        $this->assertEquals(
            'CREATE SEQUENCE myseq START WITH 1 INCREMENT BY 20 MINVALUE 1',
            $this->_platform->getCreateSequenceSQL($sequence)
        );
        $this->assertEquals(
            'ALTER SEQUENCE myseq INCREMENT BY 20',
            $this->_platform->getAlterSequenceSQL($sequence)
        );
        $this->assertEquals(
            'DROP SEQUENCE myseq',
            $this->_platform->getDropSequenceSQL('myseq')
        );
        $this->assertEquals(
            "SELECT NEXT VALUE FOR myseq",
            $this->_platform->getSequenceNextValSQL('myseq')
        );
    }


    public function testModifyLimitQuery()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user', 10, 0);
        $this->assertEquals('SELECT *, @@version as dctrn_ver FROM user ORDER BY dctrn_ver OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY', $sql);
    }

    public function testModifyLimitQueryWithEmptyOffset()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user', 10);
        $this->assertEquals('SELECT *, @@version as dctrn_ver FROM user ORDER BY dctrn_ver OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY', $sql);
    }

    public function testModifyLimitQueryWithOffset()
    {
        if ( ! $this->_platform->supportsLimitOffset()) {
            $this->markTestSkipped(sprintf('Platform "%s" does not support offsets in result limiting.', $this->_platform->getName()));
        }

        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user ORDER BY username DESC', 10, 5);
        $this->assertEquals('SELECT * FROM user ORDER BY username DESC OFFSET 5 ROWS FETCH NEXT 10 ROWS ONLY', $sql);
    }

    public function testModifyLimitQueryWithAscOrderBy()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user ORDER BY username ASC', 10);
        $this->assertEquals('SELECT * FROM user ORDER BY username ASC OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY', $sql);
    }

    public function testModifyLimitQueryWithLowercaseOrderBy()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user order by username', 10);
        $this->assertEquals('SELECT * FROM user order by username OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY', $sql);
    }

    public function testModifyLimitQueryWithDescOrderBy()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user ORDER BY username DESC', 10);
        $this->assertEquals('SELECT * FROM user ORDER BY username DESC OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY', $sql);
    }

    public function testModifyLimitQueryWithMultipleOrderBy()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user ORDER BY username DESC, usereamil ASC', 10);
        $this->assertEquals('SELECT * FROM user ORDER BY username DESC, usereamil ASC OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY', $sql);
    }

    public function testModifyLimitQueryWithSubSelect()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM (SELECT u.id as uid, u.name as uname) dctrn_result', 10);
        $this->assertEquals('SELECT *, @@version as dctrn_ver FROM (SELECT u.id as uid, u.name as uname) dctrn_result ORDER BY dctrn_ver OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY', $sql);
    }

    public function testModifyLimitQueryWithSubSelectAndOrder()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM (SELECT u.id as uid, u.name as uname) dctrn_result ORDER BY uname DESC', 10);
        $this->assertEquals('SELECT * FROM (SELECT u.id as uid, u.name as uname) dctrn_result ORDER BY uname DESC OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY', $sql);

        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM (SELECT u.id, u.name) dctrn_result ORDER BY name DESC', 10);
        $this->assertEquals('SELECT * FROM (SELECT u.id, u.name) dctrn_result ORDER BY name DESC OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY', $sql);
    }

    public function testModifyLimitQueryWithSubSelectAndMultipleOrder()
    {
        if ( ! $this->_platform->supportsLimitOffset()) {
            $this->markTestSkipped(sprintf('Platform "%s" does not support offsets in result limiting.', $this->_platform->getName()));
        }

        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM (SELECT u.id as uid, u.name as uname) dctrn_result ORDER BY uname DESC, uid ASC', 10, 5);
        $this->assertEquals('SELECT * FROM (SELECT u.id as uid, u.name as uname) dctrn_result ORDER BY uname DESC, uid ASC OFFSET 5 ROWS FETCH NEXT 10 ROWS ONLY', $sql);

        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM (SELECT u.id uid, u.name uname) dctrn_result ORDER BY uname DESC, uid ASC', 10, 5);
        $this->assertEquals('SELECT * FROM (SELECT u.id uid, u.name uname) dctrn_result ORDER BY uname DESC, uid ASC OFFSET 5 ROWS FETCH NEXT 10 ROWS ONLY', $sql);

        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM (SELECT u.id, u.name) dctrn_result ORDER BY name DESC, id ASC', 10, 5);
        $this->assertEquals('SELECT * FROM (SELECT u.id, u.name) dctrn_result ORDER BY name DESC, id ASC OFFSET 5 ROWS FETCH NEXT 10 ROWS ONLY', $sql);
    }

    public function testModifyLimitQueryWithFromColumnNames()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT a.fromFoo, fromBar FROM foo', 10);
        $this->assertEquals('SELECT a.fromFoo, fromBar, @@version as dctrn_ver FROM foo ORDER BY dctrn_ver OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY', $sql);
    }

    /**
     * @group DBAL-927
     */
    public function testModifyLimitQueryWithExtraLongQuery()
    {
        $query = 'SELECT table1.column1, table2.column2, table3.column3, table4.column4, table5.column5, table6.column6, table7.column7, table8.column8 FROM table1, table2, table3, table4, table5, table6, table7, table8 ';
        $query.= 'WHERE (table1.column1 = table2.column2) AND (table1.column1 = table3.column3) AND (table1.column1 = table4.column4) AND (table1.column1 = table5.column5) AND (table1.column1 = table6.column6) AND (table1.column1 = table7.column7) AND (table1.column1 = table8.column8) AND (table2.column2 = table3.column3) AND (table2.column2 = table4.column4) AND (table2.column2 = table5.column5) AND (table2.column2 = table6.column6) ';
        $query.= 'AND (table2.column2 = table7.column7) AND (table2.column2 = table8.column8) AND (table3.column3 = table4.column4) AND (table3.column3 = table5.column5) AND (table3.column3 = table6.column6) AND (table3.column3 = table7.column7) AND (table3.column3 = table8.column8) AND (table4.column4 = table5.column5) AND (table4.column4 = table6.column6) AND (table4.column4 = table7.column7) AND (table4.column4 = table8.column8) ';
        $query.= 'AND (table5.column5 = table6.column6) AND (table5.column5 = table7.column7) AND (table5.column5 = table8.column8) AND (table6.column6 = table7.column7) AND (table6.column6 = table8.column8) AND (table7.column7 = table8.column8)';

        $sql = $this->_platform->modifyLimitQuery($query, 10);

        $expected = 'SELECT table1.column1, table2.column2, table3.column3, table4.column4, table5.column5, table6.column6, table7.column7, table8.column8, @@version as dctrn_ver FROM table1, table2, table3, table4, table5, table6, table7, table8 ';
        $expected.= 'WHERE (table1.column1 = table2.column2) AND (table1.column1 = table3.column3) AND (table1.column1 = table4.column4) AND (table1.column1 = table5.column5) AND (table1.column1 = table6.column6) AND (table1.column1 = table7.column7) AND (table1.column1 = table8.column8) AND (table2.column2 = table3.column3) AND (table2.column2 = table4.column4) AND (table2.column2 = table5.column5) AND (table2.column2 = table6.column6) ';
        $expected.= 'AND (table2.column2 = table7.column7) AND (table2.column2 = table8.column8) AND (table3.column3 = table4.column4) AND (table3.column3 = table5.column5) AND (table3.column3 = table6.column6) AND (table3.column3 = table7.column7) AND (table3.column3 = table8.column8) AND (table4.column4 = table5.column5) AND (table4.column4 = table6.column6) AND (table4.column4 = table7.column7) AND (table4.column4 = table8.column8) ';
        $expected.= 'AND (table5.column5 = table6.column6) AND (table5.column5 = table7.column7) AND (table5.column5 = table8.column8) AND (table6.column6 = table7.column7) AND (table6.column6 = table8.column8) AND (table7.column7 = table8.column8) ';
        $expected.= 'ORDER BY dctrn_ver OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY';


        $this->assertEquals($expected, $sql);
    }

    /**
     * @group DDC-2470
     */
    public function testModifyLimitQueryWithOrderByClause()
    {
        if ( ! $this->_platform->supportsLimitOffset()) {
            $this->markTestSkipped(sprintf('Platform "%s" does not support offsets in result limiting.', $this->_platform->getName()));
        }

        $sql      = 'SELECT m0_.NOMBRE AS NOMBRE0, m0_.FECHAINICIO AS FECHAINICIO1, m0_.FECHAFIN AS FECHAFIN2 FROM MEDICION m0_ WITH (NOLOCK) INNER JOIN ESTUDIO e1_ ON m0_.ESTUDIO_ID = e1_.ID INNER JOIN CLIENTE c2_ ON e1_.CLIENTE_ID = c2_.ID INNER JOIN USUARIO u3_ ON c2_.ID = u3_.CLIENTE_ID WHERE u3_.ID = ? ORDER BY m0_.FECHAINICIO DESC';
        $expected = 'SELECT m0_.NOMBRE AS NOMBRE0, m0_.FECHAINICIO AS FECHAINICIO1, m0_.FECHAFIN AS FECHAFIN2 FROM MEDICION m0_ WITH (NOLOCK) INNER JOIN ESTUDIO e1_ ON m0_.ESTUDIO_ID = e1_.ID INNER JOIN CLIENTE c2_ ON e1_.CLIENTE_ID = c2_.ID INNER JOIN USUARIO u3_ ON c2_.ID = u3_.CLIENTE_ID WHERE u3_.ID = ? ORDER BY m0_.FECHAINICIO DESC OFFSET 5 ROWS FETCH NEXT 10 ROWS ONLY';
        $actual   = $this->_platform->modifyLimitQuery($sql, 10, 5);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @group DBAL-713
     */
    public function testModifyLimitQueryWithSubSelectInSelectList()
    {
        $sql = $this->_platform->modifyLimitQuery(
            "SELECT " .
            "u.id, " .
            "(u.foo/2) foodiv, " .
            "CONCAT(u.bar, u.baz) barbaz, " .
            "(SELECT (SELECT COUNT(*) FROM login l WHERE l.profile_id = p.id) FROM profile p WHERE p.user_id = u.id) login_count " .
            "FROM user u " .
            "WHERE u.status = 'disabled'",
            10
        );

        $this->assertEquals(

            "SELECT " .
            "u.id, " .
            "(u.foo/2) foodiv, " .
            "CONCAT(u.bar, u.baz) barbaz, " .
            "(SELECT (SELECT COUNT(*) FROM login l WHERE l.profile_id = p.id) FROM profile p WHERE p.user_id = u.id) login_count, @@version as dctrn_ver " .
            "FROM user u " .
            "WHERE u.status = 'disabled' " .
            "ORDER BY dctrn_ver OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY",
            $sql
        );
    }

    /**
     * @group DBAL-713
     */
    public function testModifyLimitQueryWithSubSelectInSelectListAndOrderByClause()
    {
        if ( ! $this->_platform->supportsLimitOffset()) {
            $this->markTestSkipped(sprintf('Platform "%s" does not support offsets in result limiting.', $this->_platform->getName()));
        }

        $sql = $this->_platform->modifyLimitQuery(
            "SELECT " .
            "u.id, " .
            "(u.foo/2) foodiv, " .
            "CONCAT(u.bar, u.baz) barbaz, " .
            "(SELECT (SELECT COUNT(*) FROM login l WHERE l.profile_id = p.id) FROM profile p WHERE p.user_id = u.id) login_count " .
            "FROM user u " .
            "WHERE u.status = 'disabled' " .
            "ORDER BY u.username DESC",
            10,
            5
        );

        $this->assertEquals(
            "SELECT " .
            "u.id, " .
            "(u.foo/2) foodiv, " .
            "CONCAT(u.bar, u.baz) barbaz, " .
            "(SELECT (SELECT COUNT(*) FROM login l WHERE l.profile_id = p.id) FROM profile p WHERE p.user_id = u.id) login_count " .
            "FROM user u " .
            "WHERE u.status = 'disabled' " .
            "ORDER BY u.username DESC " .
            "OFFSET 5 ROWS FETCH NEXT 10 ROWS ONLY",
            $sql
        );
    }

    /**
     * @group DBAL-834
     */
    public function testModifyLimitQueryWithAggregateFunctionInOrderByClause()
    {
        $sql = $this->_platform->modifyLimitQuery(
            "SELECT " .
            "MAX(heading_id) aliased, " .
            "code " .
            "FROM operator_model_operator " .
            "GROUP BY code " .
            "ORDER BY MAX(heading_id) DESC",
            1,
            0
        );

        $this->assertEquals(
            "SELECT " .
            "MAX(heading_id) aliased, " .
            "code " .
            "FROM operator_model_operator " .
            "GROUP BY code " .
            "ORDER BY MAX(heading_id) DESC " .
            "OFFSET 0 ROWS FETCH NEXT 1 ROWS ONLY",
            $sql
        );
    }

    public function testModifyLimitQueryWithFromSubquery()
    {
        $sql = $this->_platform->modifyLimitQuery("SELECT DISTINCT id_0 FROM (SELECT k0_.id AS id_0 FROM key_measure k0_ WHERE (k0_.id_zone in(2))) dctrn_result", 10);

        $expected = "SELECT DISTINCT id_0, @@version as dctrn_ver FROM (SELECT k0_.id AS id_0 FROM key_measure k0_ WHERE (k0_.id_zone in(2))) dctrn_result ORDER BY dctrn_ver OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY";

        $this->assertEquals($sql, $expected);
    }
}
