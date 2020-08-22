Feature: Run diagnostic

  Scenario: Run diagnostic
    Given a WP install

    When I run `wp option-cache diagnostic --format=csv`
    Then STDOUT should contain:
      """
      siteurl,yes,http://example.com,http://example.com,"OK: Cache is match"
      """
