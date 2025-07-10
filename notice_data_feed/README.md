# Notice Data Feed

A custom Drupal 10 module that integrates with The Gazette's REST API to fetch and display official notices. The module supports pagination and logs API interactions for debugging.

## Features

- Connects to The Gazette's `https://www.thegazette.co.uk/all-notices/notice/data.json` endpoint.
- Displays notices in a paginated view.

## Requirements

- Drupal 10+
- PHP 8.1+
- Internet access (for API calls)

## Installation

1. Download and place the module in your /modules/custom/ directory
2. Enable the module via Drush: drush en notice_data_feed or through the Drupal admin UI

## Usage

- Navigate to the path provided by the module /notice-data-feed/list to view fetched notices.
- The module fetches and displays paginated results from The Gazette API.
