<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Webconsulting\X402Paywall\Utility\Json;
use Webconsulting\X402Paywall\Utility\ScalarValue;

final class ScalarValueTest extends UnitTestCase
{
    #[Test]
    public function scalarsAreCoercedAndEmptyValuesFallBack(): void
    {
        self::assertSame('x', ScalarValue::string(' x '));
        self::assertSame('d', ScalarValue::string('  ', 'd'));
        self::assertSame('d', ScalarValue::string(['x'], 'd'));
        self::assertSame(42, ScalarValue::int('42'));
        self::assertSame(6, ScalarValue::int('', 6));
        self::assertSame(6, ScalarValue::int(null, 6));
        self::assertSame(1.5, ScalarValue::float('1.5'));
        self::assertSame(0.0, ScalarValue::float([]));
        self::assertTrue(ScalarValue::bool('yes'));
        self::assertTrue(ScalarValue::bool(1));
        self::assertFalse(ScalarValue::bool('off'));
        self::assertTrue(ScalarValue::bool('maybe', true));
    }

    #[Test]
    public function listsDropEmptyAndNonPositiveEntries(): void
    {
        self::assertSame(['/a', '/b'], ScalarValue::strings(['/a', '', null, '/b', ['/c']]));
        self::assertSame([], ScalarValue::strings('/a'));
        self::assertSame([42, 7], ScalarValue::positiveInts(['42', 0, '-1', 7, 'x']));
    }

    #[Test]
    public function jsonObjectsBecomeStringKeyedArrays(): void
    {
        self::assertSame(['a' => 1, 'b' => ['c' => null]], Json::decodeObject('{"a":1,"b":{"c":null}}'));
        self::assertSame([], Json::decodeObject('"scalar"'));
        self::assertSame([], Json::object(null));
        self::assertSame('{"a":"x/y"}', Json::encode(['a' => 'x/y'], JSON_UNESCAPED_SLASHES));

        $this->expectException(\JsonException::class);
        Json::decodeObject('nope');
    }
}
