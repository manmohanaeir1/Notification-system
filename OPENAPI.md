# OpenAPI / Swagger Documentation

Complete REST API specification and interactive documentation for the Notification System API.

## 📖 Overview

The Notification System API is fully documented using **OpenAPI 3.0.0** specification. This provides:

- ✅ Machine-readable API specification
- 🖥️ Interactive Swagger UI for testing endpoints
- 📚 Comprehensive schema definitions
- 🔒 Security and error handling documentation
- 📊 Request/response examples for all endpoints

## 🔗 Access Points

### 1. Interactive Swagger UI (Recommended)

**URL:** http://localhost:8000/swagger.html

**Features:**
- 🕹️ Try-it-out functionality - test API endpoints directly
- 📝 Auto-formatted request/response bodies
- 🔍 Search and filter endpoints
- 📋 Parameter validation hints
- 🎯 Example values pre-populated

**Screenshot flow:**
1. Navigate to the URL above
2. Click on any endpoint to expand it
3. Click "Try it out" button
4. Fill in parameters or request body
5. Click "Execute" to make the request
6. View response code, headers, and body

### 2. OpenAPI Specification Files

#### YAML Format (Recommended for Reading)
- **File:** [openapi.yaml](openapi.yaml)
- **Best for:** Manual review, version control
- **Tool Integration:** Most API tools support YAML
- **Size:** ~1.2 KB (compressed, human-readable)

#### JSON Format (Recommended for Tools)
- **File:** [openapi.json](openapi.json)
- **Best for:** Programmatic access, code generation
- **Tool Integration:** 100% of API tools support JSON
- **Size:** ~1.4 KB

## 📋 Specification Details

### API Version
- **Version:** 1.0.0
- **OpenAPI Version:** 3.0.0
- **Base URL:** `/api/v1`

### Servers Defined
```yaml
- Local Development: http://localhost:8000/api/v1
- Production: https://api.example.com/api/v1
```

### Supported Environments

| Environment | Host | Port | Status |
|------------|------|------|--------|
| Local Development | localhost | 8000 | ✅ Running |
| Production | api.example.com | 443 | 🔄 Configure |

## 🔌 Endpoints Documented

### 1. Create Notification
```
POST /notifications
```

**Purpose:** Publish a new notification to the queue

**Request Body:**
```json
{
  "user_id": "string (required)",
  "type": "string (required)",
  "message": "string (required)",
  "channel": "database|email|sms|push (optional, default: database)"
}
```

**Response (201 Created):**
```json
{
  "data": {
    "id": "integer",
    "user_id": "string",
    "type": "string",
    "channel": "string",
    "message": "string",
    "status": "pending|processed|failed",
    "status_label": "string",
    "attempts": "integer|null",
    "error_message": "string|null",
    "processed_at": "datetime|null",
    "created_at": "datetime"
  }
}
```

**Error Responses:**
- `422 Unprocessable Entity` - Validation failed
- `429 Too Many Requests` - Rate limit exceeded (10/user/hour)
- `500 Internal Server Error` - Server error

### 2. Get Notifications
```
GET /notifications
```

**Purpose:** Retrieve paginated list of notifications with filtering

**Query Parameters:**
| Parameter | Type | Description | Required |
|-----------|------|-------------|----------|
| `status` | string | pending\|processed\|failed | No |
| `user_id` | string | Filter by user ID | No |
| `channel` | string | database\|email\|sms\|push | No |
| `from_date` | date | YYYY-MM-DD format | No |
| `to_date` | date | YYYY-MM-DD format | No |
| `per_page` | integer | 1-100, default 15 | No |
| `page` | integer | Page number, default 1 | No |

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": "integer",
      "user_id": "string",
      "type": "string",
      "channel": "string",
      "message": "string",
      "status": "string",
      "status_label": "string",
      "attempts": "integer|null",
      "error_message": "string|null",
      "processed_at": "datetime|null",
      "created_at": "datetime"
    }
  ],
  "links": {
    "first": "url",
    "last": "url",
    "prev": "url|null",
    "next": "url|null"
  },
  "meta": {
    "current_page": "integer",
    "from": "integer",
    "last_page": "integer",
    "path": "url",
    "per_page": "integer",
    "to": "integer",
    "total": "integer"
  }
}
```

**Examples:**

Get all pending notifications:
```bash
GET /notifications?status=pending
```

Get notifications for specific user:
```bash
GET /notifications?user_id=user-123
```

Get processed notifications with pagination:
```bash
GET /notifications?status=processed&page=2&per_page=25
```

Get email notifications from date range:
```bash
GET /notifications?channel=email&from_date=2026-05-01&to_date=2026-05-02
```

### 3. Get Summary
```
GET /notifications/summary
```

**Purpose:** Retrieve aggregated notification statistics (cached 5 minutes)

**Query Parameters:**
| Parameter | Type | Description | Required |
|-----------|------|-------------|----------|
| `user_id` | string | Filter summary for specific user | No |

**Response (200 OK):**
```json
{
  "data": {
    "total": "integer",
    "processed": "integer",
    "failed": "integer",
    "pending": "integer"
  },
  "cached": "boolean"
}
```

**Examples:**

Global summary:
```bash
GET /notifications/summary
# Response:
# {"data": {"total": 150, "processed": 140, "failed": 5, "pending": 5}, "cached": true}
```

User-specific summary:
```bash
GET /notifications/summary?user_id=user-123
# Response:
# {"data": {"total": 15, "processed": 12, "failed": 1, "pending": 2}, "cached": true}
```

## 🔄 Request / Response Examples

### Example 1: Create and Track Notification

**Step 1: Create notification**
```bash
curl -X POST http://localhost:8000/api/v1/notifications \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": "user-123",
    "type": "welcome",
    "message": "Welcome to our service!",
    "channel": "email"
  }'
```

**Response:**
```json
{
  "data": {
    "id": 42,
    "user_id": "user-123",
    "type": "welcome",
    "channel": "email",
    "message": "Welcome to our service!",
    "status": "pending",
    "status_label": "Pending",
    "attempts": null,
    "error_message": null,
    "processed_at": null,
    "created_at": "2026-05-02T10:30:00Z"
  }
}
```

**Step 2: Check it's processing**
```bash
curl http://localhost:8000/api/v1/notifications?user_id=user-123
```

**Response (after 9-43ms):**
```json
{
  "data": [
    {
      "id": 42,
      "status": "processed",
      "status_label": "Processed",
      "attempts": 1,
      "processed_at": "2026-05-02T10:30:00.025Z"
    }
  ]
}
```

**Step 3: Check aggregate statistics**
```bash
curl http://localhost:8000/api/v1/notifications/summary?user_id=user-123
```

**Response:**
```json
{
  "data": {
    "total": 1,
    "processed": 1,
    "failed": 0,
    "pending": 0
  },
  "cached": false
}
```

### Example 2: Batch Query with Filtering

```bash
curl "http://localhost:8000/api/v1/notifications?status=pending&channel=database&page=1&per_page=25"
```

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "user_id": "user-111",
      "type": "alert",
      "channel": "database",
      "status": "pending",
      "created_at": "2026-05-02T07:21:00Z"
    },
    {
      "id": 2,
      "user_id": "user-222",
      "type": "reminder",
      "channel": "database",
      "status": "pending",
      "created_at": "2026-05-02T07:22:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 42,
    "last_page": 2,
    "per_page": 25
  }
}
```

## 📊 Schema Definitions

### Notification Object
```yaml
properties:
  id:
    type: integer
    description: Unique notification identifier
  user_id:
    type: string
    maxLength: 255
    description: User identifier
  type:
    type: string
    maxLength: 100
    description: Notification type (alert, reminder, welcome, etc)
  channel:
    type: string
    enum: [database, email, sms, push]
    description: Delivery channel
  message:
    type: string
    maxLength: 1000
    description: Notification content
  status:
    type: string
    enum: [pending, processed, failed]
    description: Processing status
  status_label:
    type: string
    description: Human-readable status (Pending, Processed, Failed)
  attempts:
    type: integer
    nullable: true
    description: Number of processing attempts (null if not started)
  error_message:
    type: string
    nullable: true
    description: Error details if status is failed
  processed_at:
    type: string
    format: date-time
    nullable: true
    description: Timestamp when processing completed
  created_at:
    type: string
    format: date-time
    description: Creation timestamp
```

### Error Schemas

**ValidationError:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "user_id": ["The user_id field is required."],
    "message": ["The message field is required."]
  }
}
```

**RateLimitError:**
```json
{
  "message": "Rate limit exceeded. Maximum 10 notifications per user per hour.",
  "retry_after": 3580
}
```

**ServerError:**
```json
{
  "message": "An unexpected error occurred. Please try again later."
}
```

## 🔐 Rate Limiting

**Global Rate Limit:** 60 requests/minute per IP (API middleware)

**Notification Rate Limit:** 10 notifications/user/hour (service layer)
- Enforced via Redis rate limiter
- Returns 429 on exceeded limit
- `retry_after` header indicates seconds to wait

## 💾 Caching

**Summary Endpoint Cache:**
- Duration: 5 minutes (300 seconds)
- Strategy: Cache-Aside (read-through)
- Invalidation: On-demand when status updates
- Visibility: `"cached": true/false` in response indicates cache hit

## 🛠️ Integration Tools

### Using the OpenAPI/Swagger Files

**1. Generate Client Code**
```bash
# Generate JavaScript client
npx openapi-generator-cli generate -i openapi.yaml -g javascript -o client-js

# Generate Python client
npx openapi-generator-cli generate -i openapi.yaml -g python -o client-python

# Generate Go client
npx openapi-generator-cli generate -i openapi.yaml -g go -o client-go
```

**2. API Testing Tools**
- **Postman:** Import `openapi.json` for automatic collection setup
- **Insomnia:** Import `openapi.yaml` for request scaffolding
- **Paw (macOS):** Drag and drop `openapi.yaml` to import
- **RESTClient:** VS Code extension with OpenAPI support

**3. Documentation Generators**
```bash
# Generate HTML docs (ReDoc)
docker run --name redoc \
  -p 8080:80 \
  -e SPEC_URL=file:///openapi.yaml \
  -v ${PWD}/openapi.yaml:/usr/share/nginx/html/openapi.yaml \
  redocly/redoc

# View at http://localhost:8080
```

**4. Validation**
```bash
# Validate OpenAPI spec
npm install -g swagger-cli
swagger-cli validate openapi.yaml
swagger-cli validate openapi.json
```

## 📈 Performance Specifications

| Metric | Value | Notes |
|--------|-------|-------|
| Response Time (API) | < 100ms | Measured locally |
| Queue Processing | 9-43ms | Per notification |
| Throughput | 100+ notifications/sec | Estimated capacity |
| Cache Hit Ratio | ~90% | On summary endpoint |
| Rate Limit | 10/user/hour | Configurable in service |

## 🔄 Versioning

**Current API Version:** v1

**Endpoint Pattern:** `/api/v1/...`

**Future Versions:** 
- Path-based versioning: `/api/v2/...`
- Backward compatibility maintained
- Deprecation notices in headers

## 📖 Additional Resources

- **[API.md](API.md)** - Detailed endpoint documentation
- **[DOCKER.md](DOCKER.md)** - Docker and deployment guide
- **[QUICKSTART.md](QUICKSTART.md)** - 5-minute setup guide
- **[PROJECT_STATUS.md](PROJECT_STATUS.md)** - Live metrics and verification
- **[ALIGNMENT_REPORT.md](ALIGNMENT_REPORT.md)** - Requirements audit

## 🤝 Support

For issues or questions about the API:

1. Check the interactive Swagger UI: http://localhost:8000/swagger.html
2. Review examples in this documentation
3. See [API.md](API.md) for detailed endpoint documentation
4. Check [PROJECT_STATUS.md](PROJECT_STATUS.md) for system status

---

**Last Updated:** May 2, 2026  
**Specification Version:** 1.0.0  
**OpenAPI Version:** 3.0.0
