padimodxketo
============

A MODX snippet that creates a simple web service connecting Padiact to Marketo.

Padiact is configured to post lead data via a webhook to this snippet, which
then uses Marketo's REST API to create a new lead and add that lead to a list.

As a fallback, this snippet uses the Brace.io Data API to add lead data
to a Google Doc spreadsheet in the event of a cURL error or Marketo error.
