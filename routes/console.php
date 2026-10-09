<?php

// THE SCHEDULE: every timed job in one place (run by one cron line, every minute).
// every minute: queue:work --stop-when-empty --max-time=50
// hourly: proactive checks (orders, payments, stock)
// daily: sales/traffic/ads/cash checks, Meta import, reconcile, prune page views
// 09:00 Africa/Cairo: morning brief
// nightly: Adorna import, FP&A memos (by 07:00), database backup
// weekly: margin/trend/budget checks, weekly briefing, restore test
