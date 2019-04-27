# oddc

## Metadata

- **Source**

   This is a free text string passed in on the query string as source=...
   It is stored on the Contribution's built-in source field.

- **Campaign**

   This is the CiviCRM campaign field. It is stored on both the Contribution and
   the recurring Contribution recordss.

- **Project**

   This is a string and is stored on a custom field on contributions.

## Email Dashboard

### Configuration.

Stored as a JSON blob with key `oddc_dashboards`. IT looks like this:

    {
        mailingLists: [123, 456, 789], // group IDs to show in dash.
    }
