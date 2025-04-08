## Prerequisites

```bash
composer require elasticsearch/elasticsearch monolog/monolog
```

## Create/Configure the `.env` File

Add the following environment variables:

```env
ES_SCHEME=http
ES_USERNAME=your_username
ES_PASSWORD=your_password
ES_NODES=<HOST IP/NAME>
ES_PORT=9200
```

Replace `your_username`, `your_password`, and `HOST IP/NAME` with your actual Elasticsearch credentials and elastic host IP or name.
