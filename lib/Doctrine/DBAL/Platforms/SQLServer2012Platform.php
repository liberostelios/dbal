<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\DBAL\Platforms;

use Doctrine\DBAL\Schema\Sequence;

/**
 * Platform to ensure compatibility of Doctrine with Microsoft SQL Server 2012 version.
 *
 * Differences to SQL Server 2008 and before are that sequences are introduced.
 *
 * @author Steve Müller <st.mueller@dzh-online.de>
 */
class SQLServer2012Platform extends SQLServer2008Platform
{
    /**
     * {@inheritdoc}
     */
    public function getAlterSequenceSQL(Sequence $sequence)
    {
        return 'ALTER SEQUENCE ' . $sequence->getQuotedName($this) .
               ' INCREMENT BY ' . $sequence->getAllocationSize();
    }

    /**
     * {@inheritdoc}
     */
    public function getCreateSequenceSQL(Sequence $sequence)
    {
        return 'CREATE SEQUENCE ' . $sequence->getQuotedName($this) .
               ' START WITH ' . $sequence->getInitialValue() .
               ' INCREMENT BY ' . $sequence->getAllocationSize() .
               ' MINVALUE ' . $sequence->getInitialValue();
    }

    /**
     * {@inheritdoc}
     */
    public function getDropSequenceSQL($sequence)
    {
        if ($sequence instanceof Sequence) {
            $sequence = $sequence->getQuotedName($this);
        }

        return 'DROP SEQUENCE ' . $sequence;
    }

    /**
     * {@inheritdoc}
     */
    public function getListSequencesSQL($database)
    {
        return 'SELECT seq.name,
                       CAST(
                           seq.increment AS VARCHAR(MAX)
                       ) AS increment, -- CAST avoids driver error for sql_variant type
                       CAST(
                           seq.start_value AS VARCHAR(MAX)
                       ) AS start_value -- CAST avoids driver error for sql_variant type
                FROM   sys.sequences AS seq';
    }

    /**
     * {@inheritdoc}
     */
    public function getSequenceNextValSQL($sequenceName)
    {
        return 'SELECT NEXT VALUE FOR ' . $sequenceName;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsSequences()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * Returns Microsoft SQL Server 2012 specific keywords class
     */
    protected function getReservedKeywordsClass()
    {
        return 'Doctrine\DBAL\Platforms\Keywords\SQLServer2012Keywords';
    }

    /**
     * Adds SQL Server 2012 specific LIMIT clause to the query
     * @inheritdoc
     */
    protected function doModifyLimitQuery($query, $limit, $offset = NULL)
    {
        if($limit === null && $offset === null) {
            return $query;
        }

        // Queries using OFFSET... FETCH MUST have an ORDER BY clause
        if (!preg_match("/ORDER BY ([a-z0-9\.\[\], \t_]|[a-z_]+\([a-z0-9\.\[\], \t_]+\))+\s*$/i", $query)) {
            $query .= " ORDER BY dctrn_ver";

            $from = $this->findOuterFrom($query);
            //TODO handle $from === false

            $query = substr_replace($query, ", @@version as dctrn_ver", $from, 0);
        }

        // This looks like MYSQL, but limit/offset are in inverse positions
        // Supposedly SQL:2008 core standard.
        if ($offset !== null) {
            $query .= " OFFSET " . (int)$offset . " ROWS";
            if ($limit !== null) {
                $query .= " FETCH NEXT " . (int)$limit . " ROWS ONLY";
            }
        } elseif ($limit !== null) {
            // Can't have FETCH NEXT n ROWS ONLY without OFFSET n ROWS - per TSQL spec
            $query .= " OFFSET 0 ROWS FETCH NEXT " . (int)$limit . " ROWS ONLY";
        }

        return $query;
    }

    /**
     * recursive function to find the outermost FROM clause in a SELECT query
     *
     * @param string $query the SQL query
     * @param int $pos position of previous FROM instance, if any
     * @return bool|int
     */
    protected function findOuterFrom($query, $pos = 0)
    {
        $needle = " from ";
        if(false === ($found = stripos($query, $needle, $pos)))
            return false;

        $before = substr_count($query, "(", 0, $found) - substr_count($query, ")", 0, $found);
        $after = substr_count($query, "(", $found + strlen($needle)) - substr_count($query, ")", $found + strlen($needle));

        // $needle was found outside any parens.
        if(!$before && !$after) {
            return $found;
        }
        return $this->findOuterFrom($query, $found + strlen($needle));
    }
}
