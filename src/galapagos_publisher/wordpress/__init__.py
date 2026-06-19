"""WordPress REST API integration."""

from .client import WordPressClient
from .publisher import PublishResult, publish_page

__all__ = ["WordPressClient", "publish_page", "PublishResult"]
