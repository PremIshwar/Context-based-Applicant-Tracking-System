import httpx
import json
import re
import logging
from config import API_KEY, MODEL, BASE_URL

logger = logging.getLogger(__name__)


async def call_glm(system_prompt: str, user_prompt: str, temperature: float = 0.3, max_tokens: int = 4096, retries: int = 2) -> str:
    headers = {
        "Authorization": f"Bearer {API_KEY}",
        "Content-Type": "application/json",
    }
    payload = {
        "model": MODEL,
        "messages": [
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": user_prompt},
        ],
        "temperature": temperature,
        "max_tokens": max_tokens,
    }

    last_error = None
    for attempt in range(retries + 1):
        try:
            async with httpx.AsyncClient(timeout=180.0) as client:
                response = await client.post(
                    f"{BASE_URL}/chat/completions",
                    headers=headers,
                    json=payload,
                )
                response.raise_for_status()
                data = response.json()
                content = data["choices"][0]["message"]["content"]
                if content and content.strip():
                    return content
                # Empty response — retry with a nudge
                logger.warning("GLM returned empty content (attempt %d)", attempt + 1)
                payload["messages"].append({"role": "assistant", "content": ""})
                payload["messages"].append({"role": "user", "content": "Please provide your response."})
                last_error = "empty_response"
        except httpx.HTTPStatusError as e:
            logger.error("GLM API error %d: %s", e.response.status_code, e.response.text[:200])
            last_error = e
            if attempt < retries:
                import asyncio
                await asyncio.sleep(2 ** attempt)
            else:
                raise
        except Exception as e:
            logger.error("GLM call failed: %s", e)
            last_error = e
            if attempt < retries:
                import asyncio
                await asyncio.sleep(2 ** attempt)
            else:
                raise

    if last_error:
        raise last_error
    return ""


def _extract_json(text: str) -> dict:
    """Robustly extract JSON from GLM output, handling code blocks, trailing commas, etc."""
    text = text.strip()

    # Strip markdown code fences
    if "```" in text:
        lines = text.split("\n")
        lines = [l for l in lines if not l.strip().startswith("```")]
        text = "\n".join(lines)

    # Try direct parse
    try:
        return json.loads(text)
    except json.JSONDecodeError:
        pass

    # Find outermost braces
    start = text.find("{")
    end = text.rfind("}") + 1
    if start != -1 and end > start:
        candidate = text[start:end]
        try:
            return json.loads(candidate)
        except json.JSONDecodeError:
            pass

        # Fix trailing commas before } or ]
        fixed = re.sub(r',\s*([}\]])', r'\1', candidate)
        try:
            return json.loads(fixed)
        except json.JSONDecodeError:
            pass

        # Fix single quotes to double quotes
        fixed2 = fixed.replace("'", '"')
        try:
            return json.loads(fixed2)
        except json.JSONDecodeError:
            pass

    logger.error("Could not extract JSON from GLM output (first 500 chars): %s", text[:500])
    raise ValueError(f"GLM returned unparseable output")


async def call_glm_json(system_prompt: str, user_prompt: str, temperature: float = 0.2, max_tokens: int = 4096) -> dict:
    raw = await call_glm(system_prompt, user_prompt, temperature, max_tokens)
    return _extract_json(raw)
