# Security Policy

The security of codesaur/raptor is taken seriously.

If you discover a security vulnerability, please **do not** report it through public GitHub issues.

---

## Reporting a Vulnerability

Please report security issues privately via email:

**codesaur@gmail.com**

When reporting, include:

- A clear description of the issue
- Steps to reproduce (if possible)
- Potential impact
- Affected versions (if known)

---

## Response Timeline

- You can expect an initial response within **3-7 days**
- We will work to assess and fix the issue as quickly as possible
- Once fixed, an appropriate disclosure will be made if necessary

---

## Supported Versions

Security updates are provided for the latest stable release only.

---

## Common Security Considerations

When contributing to or deploying Raptor, be mindful of:

- **JWT Secret** - Keep `RAPTOR_JWT_SECRET` confidential; never commit `.env` to version control
- **Database credentials** - Store all credentials in `.env`, never hardcode them
- **File uploads** - The framework validates file types and sizes; do not bypass these checks
- **RBAC permissions** - Always verify user permissions before granting access to protected resources
- **CSRF protection** - Dashboard POST/PUT/PATCH/DELETE requests are protected by CsrfMiddleware. Use `csrfFetch()` for all state-changing requests in dashboard JS
- **Login rate limiting** - Failed login attempts are tracked in logs; 10+ failures within 15 minutes triggers lockout
- **Password reset cooldown** - Forgot password requests are rate-limited per email address via `RAPTOR_PASSWORD_RESET_MINUTES`
- **SQL injection** - All user input in JSON context filters is sanitized with allowlist regex; ORDER BY and LIMIT are validated
- **OpenAI API Key** - If using AI features, keep `RAPTOR_OPENAI_API_KEY` secure

---

## Responsible Disclosure

We kindly ask reporters to follow responsible disclosure practices and allow time for the issue to be resolved before public disclosure.

---

Thank you for helping keep codesaur/raptor secure.
