# Email Newsletter System — Инструкции для Claude Code

> Система подписок "Daily Crypto Brief": сбор email'ов, управление подписчиками,
> AI-генерация дайджеста с ручным редактированием, отправка по кнопке из админки.

---

## Обзор функционала

1. **Подписка** — форма на сайте (уже есть на главной в sidebar), double opt-in
2. **Управление подписчиками** — список в админке, фильтры, экспорт, отписка
3. **Генерация дайджеста** — AI собирает топ-статьи за сутки, формирует письмо
4. **Редактирование** — админ правит текст, тему, превью в визуальном редакторе
5. **Отправка** — по кнопке, batch-рассылка через Symfony Messenger + очередь

---

## База данных

### Таблица подписчиков

```sql
CREATE TABLE newsletter_subscribers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    status ENUM('pending', 'active', 'unsubscribed', 'bounced') NOT NULL DEFAULT 'pending',
    token VARCHAR(64) NOT NULL,                -- уникальный токен для confirm/unsubscribe
    confirmed_at DATETIME DEFAULT NULL,
    unsubscribed_at DATETIME DEFAULT NULL,
    source VARCHAR(50) DEFAULT 'homepage',     -- откуда подписался: homepage, article, footer
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_site_email (site_id, email),
    INDEX idx_site_status (site_id, status),
    INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Таблица выпусков (issues)

```sql
CREATE TABLE newsletter_issues (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT UNSIGNED NOT NULL,
    subject VARCHAR(255) NOT NULL,
    preview_text VARCHAR(255) DEFAULT NULL,     -- текст превью в inbox
    content_html LONGTEXT NOT NULL,             -- HTML письма
    content_json JSON DEFAULT NULL,             -- структурированные данные для редактора
    status ENUM('draft', 'ready', 'sending', 'sent', 'failed') NOT NULL DEFAULT 'draft',
    generated_by ENUM('ai', 'manual') NOT NULL DEFAULT 'ai',
    scheduled_at DATETIME DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,
    recipients_count INT UNSIGNED DEFAULT 0,
    sent_count INT UNSIGNED DEFAULT 0,
    failed_count INT UNSIGNED DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    INDEX idx_site_status (site_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Таблица логов отправки

```sql
CREATE TABLE newsletter_send_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    issue_id INT UNSIGNED NOT NULL,
    subscriber_id INT UNSIGNED NOT NULL,
    status ENUM('queued', 'sent', 'failed') NOT NULL DEFAULT 'queued',
    error_message TEXT DEFAULT NULL,
    sent_at DATETIME DEFAULT NULL,
    FOREIGN KEY (issue_id) REFERENCES newsletter_issues(id) ON DELETE CASCADE,
    FOREIGN KEY (subscriber_id) REFERENCES newsletter_subscribers(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_issue_subscriber (issue_id, subscriber_id),
    INDEX idx_issue_status (issue_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Entities

### NewsletterSubscriber

```php
// src/Entity/NewsletterSubscriber.php

#[ORM\Entity(repositoryClass: NewsletterSubscriberRepository::class)]
#[ORM\Table(name: 'newsletter_subscribers')]
#[ORM\UniqueConstraint(columns: ['site_id', 'email'])]
class NewsletterSubscriber implements SiteAwareInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    #[ORM\Column(type: 'string', enumType: SubscriberStatus::class)]
    private SubscriberStatus $status = SubscriberStatus::PENDING;

    #[ORM\Column(length: 64)]
    private string $token;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $unsubscribedAt = null;

    #[ORM\Column(length: 50, options: ['default' => 'homepage'])]
    private string $source = 'homepage';

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->token = bin2hex(random_bytes(32));
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function confirm(): void
    {
        $this->status = SubscriberStatus::ACTIVE;
        $this->confirmedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function unsubscribe(): void
    {
        $this->status = SubscriberStatus::UNSUBSCRIBED;
        $this->unsubscribedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ... getters
}
```

```php
// src/Entity/Enum/SubscriberStatus.php

enum SubscriberStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case UNSUBSCRIBED = 'unsubscribed';
    case BOUNCED = 'bounced';
}
```

### NewsletterIssue

```php
// src/Entity/NewsletterIssue.php

#[ORM\Entity(repositoryClass: NewsletterIssueRepository::class)]
#[ORM\Table(name: 'newsletter_issues')]
class NewsletterIssue implements SiteAwareInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $subject;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $previewText = null;

    #[ORM\Column(type: 'text')]
    private string $contentHtml;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $contentJson = null;

    #[ORM\Column(type: 'string', enumType: IssueStatus::class)]
    private IssueStatus $status = IssueStatus::DRAFT;

    #[ORM\Column(length: 10, options: ['default' => 'ai'])]
    private string $generatedBy = 'ai';

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true, 'default' => 0])]
    private int $recipientsCount = 0;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true, 'default' => 0])]
    private int $sentCount = 0;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true, 'default' => 0])]
    private int $failedCount = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    // ... constructor, getters, setters
}
```

```php
// src/Entity/Enum/IssueStatus.php

enum IssueStatus: string
{
    case DRAFT = 'draft';
    case READY = 'ready';
    case SENDING = 'sending';
    case SENT = 'sent';
    case FAILED = 'failed';
}
```

---

## Фронтенд: подписка

### Форма подписки (уже на главной, нужен backend)

```twig
{# templates/components/newsletter_form.html.twig #}
{# Используется в sidebar, footer, и отдельных местах #}

<div class="newsletter-form" id="newsletter-form" data-url="{{ path('newsletter_subscribe') }}">
    <div class="newsletter-form-fields" id="newsletter-fields">
        <input type="email" 
               id="newsletter-email" 
               placeholder="Your email" 
               class="tool-input"
               required
               autocomplete="email">
        <button type="button" 
                id="newsletter-submit" 
                class="tool-btn"
                style="margin-top: var(--space-2);">
            Subscribe
        </button>
    </div>
    <p class="newsletter-note">Free. No spam. Unsubscribe anytime.</p>
    
    {# Состояния — показываются JS'ом #}
    <div class="newsletter-success" id="newsletter-success" hidden>
        <p>Check your email to confirm your subscription.</p>
    </div>
    <div class="newsletter-error" id="newsletter-error" hidden>
        <p id="newsletter-error-text"></p>
    </div>
</div>
```

```javascript
// assets/js/newsletter.js

document.querySelectorAll('.newsletter-form').forEach(form => {
    const url = form.dataset.url;
    const emailInput = form.querySelector('input[type="email"]');
    const submitBtn = form.querySelector('button');
    const fields = form.querySelector('.newsletter-form-fields');
    const success = form.querySelector('.newsletter-success');
    const error = form.querySelector('.newsletter-error');
    const errorText = form.querySelector('.newsletter-error-text');

    submitBtn?.addEventListener('click', async () => {
        const email = emailInput.value.trim();
        if (!email || !emailInput.checkValidity()) {
            emailInput.reportValidity();
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = 'Subscribing...';
        error.hidden = true;

        try {
            const resp = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, source: form.closest('[data-source]')?.dataset.source || 'homepage' }),
            });

            const data = await resp.json();

            if (resp.ok) {
                fields.hidden = true;
                success.hidden = false;
            } else {
                errorText.textContent = data.error || 'Something went wrong. Try again.';
                error.hidden = false;
                submitBtn.disabled = false;
                submitBtn.textContent = 'Subscribe';
            }
        } catch {
            errorText.textContent = 'Connection error. Please try again.';
            error.hidden = false;
            submitBtn.disabled = false;
            submitBtn.textContent = 'Subscribe';
        }
    });

    // Enter key
    emailInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') submitBtn.click();
    });
});
```

### Контроллер подписки

```php
// src/Controller/Frontend/NewsletterController.php

#[Route('/newsletter/subscribe', name: 'newsletter_subscribe', methods: ['POST'])]
public function subscribe(
    Request $request,
    SiteContext $siteContext,
    NewsletterSubscriberRepository $repo,
    EntityManagerInterface $em,
    NewsletterMailer $mailer,
): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    $email = trim($data['email'] ?? '');
    $source = $data['source'] ?? 'homepage';

    // Валидация email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $this->json(['error' => 'Please enter a valid email address.'], 400);
    }

    $site = $siteContext->getSite();

    // Проверить существующего подписчика
    $existing = $repo->findOneBy(['site' => $site, 'email' => $email]);

    if ($existing) {
        if ($existing->getStatus() === SubscriberStatus::ACTIVE) {
            return $this->json(['error' => 'This email is already subscribed.'], 409);
        }
        if ($existing->getStatus() === SubscriberStatus::PENDING) {
            // Переотправить confirmation
            $mailer->sendConfirmation($existing);
            return $this->json(['message' => 'Confirmation email resent.']);
        }
        if ($existing->getStatus() === SubscriberStatus::UNSUBSCRIBED) {
            // Реактивация — новый pending + новый token
            $existing->resubscribe(); // сбрасывает status в pending, генерит новый token
            $em->flush();
            $mailer->sendConfirmation($existing);
            return $this->json(['message' => 'Check your email to reconfirm.']);
        }
    }

    // Новый подписчик
    $subscriber = new NewsletterSubscriber();
    $subscriber->setSite($site);
    $subscriber->setEmail(mb_strtolower($email));
    $subscriber->setSource($source);
    $subscriber->setIpAddress($request->getClientIp());
    $subscriber->setUserAgent($request->headers->get('User-Agent'));

    $em->persist($subscriber);
    $em->flush();

    // Отправить confirmation email (double opt-in)
    $mailer->sendConfirmation($subscriber);

    return $this->json(['message' => 'Check your email to confirm.']);
}

#[Route('/newsletter/confirm/{token}', name: 'newsletter_confirm', methods: ['GET'])]
public function confirm(
    string $token,
    NewsletterSubscriberRepository $repo,
    EntityManagerInterface $em,
): Response
{
    $subscriber = $repo->findOneBy(['token' => $token]);

    if (!$subscriber) {
        throw $this->createNotFoundException('Invalid confirmation link.');
    }

    if ($subscriber->getStatus() === SubscriberStatus::ACTIVE) {
        // Уже подтверждён
        return $this->render('frontend/newsletter/already_confirmed.html.twig');
    }

    $subscriber->confirm();
    $em->flush();

    return $this->render('frontend/newsletter/confirmed.html.twig', [
        'subscriber' => $subscriber,
    ]);
}

#[Route('/newsletter/unsubscribe/{token}', name: 'newsletter_unsubscribe', methods: ['GET'])]
public function unsubscribe(
    string $token,
    NewsletterSubscriberRepository $repo,
    EntityManagerInterface $em,
): Response
{
    $subscriber = $repo->findOneBy(['token' => $token]);

    if (!$subscriber) {
        throw $this->createNotFoundException('Invalid unsubscribe link.');
    }

    $subscriber->unsubscribe();
    $em->flush();

    return $this->render('frontend/newsletter/unsubscribed.html.twig', [
        'subscriber' => $subscriber,
    ]);
}
```

### Email-шаблон подтверждения

```twig
{# templates/email/newsletter_confirmation.html.twig #}

{% block subject %}Confirm your subscription to {{ site.name }}{% endblock %}

{% block body %}
<p>Hi,</p>
<p>You've requested to subscribe to <strong>{{ site.name }} Daily Crypto Brief</strong>.</p>
<p>Please confirm your subscription by clicking the button below:</p>
<p style="text-align: center; margin: 32px 0;">
    <a href="{{ url('newsletter_confirm', {token: subscriber.token}) }}"
       style="background: #2563eb; color: #fff; padding: 12px 32px; 
              border-radius: 6px; text-decoration: none; font-weight: 600;">
        Confirm Subscription
    </a>
</p>
<p>If you didn't request this, you can safely ignore this email.</p>
<p style="color: #888; font-size: 13px;">
    {{ site.name }} · {{ site.domain }}
</p>
{% endblock %}
```

### Сервис отправки

```php
// src/Service/Newsletter/NewsletterMailer.php

class NewsletterMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private SiteContext $siteContext,
        private Environment $twig,
    ) {}

    public function sendConfirmation(NewsletterSubscriber $subscriber): void
    {
        $site = $this->siteContext->getSite();

        $html = $this->twig->render('email/newsletter_confirmation.html.twig', [
            'subscriber' => $subscriber,
            'site' => $site,
        ]);

        $email = (new Email())
            ->from(new Address("noreply@{$site->getDomain()}", $site->getName()))
            ->to($subscriber->getEmail())
            ->subject("Confirm your subscription to {$site->getName()}")
            ->html($html);

        $this->mailer->send($email);
    }

    public function sendIssue(NewsletterIssue $issue, NewsletterSubscriber $subscriber): void
    {
        $site = $issue->getSite();
        $unsubscribeUrl = "https://{$site->getDomain()}/newsletter/unsubscribe/{$subscriber->getToken()}";

        // Вставить unsubscribe footer в HTML
        $html = $issue->getContentHtml();
        $html = str_replace('{{UNSUBSCRIBE_URL}}', $unsubscribeUrl, $html);

        $email = (new Email())
            ->from(new Address("noreply@{$site->getDomain()}", $site->getName()))
            ->to($subscriber->getEmail())
            ->subject($issue->getSubject())
            ->html($html);

        // List-Unsubscribe header (стандарт RFC 2369)
        $email->getHeaders()->addTextHeader('List-Unsubscribe', "<{$unsubscribeUrl}>");
        $email->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');

        if ($issue->getPreviewText()) {
            // Preview text через скрытый span в начале HTML
            // (уже должен быть в шаблоне)
        }

        $this->mailer->send($email);
    }
}
```

---

## Админка: управление подписчиками

### Список подписчиков

```php
// src/Controller/Admin/NewsletterAdminController.php

#[Route('/admin/newsletter', name: 'admin_newsletter')]
class NewsletterAdminController extends AbstractController
{
    #[Route('/subscribers', name: 'admin_newsletter_subscribers')]
    public function subscribers(
        Request $request,
        NewsletterSubscriberRepository $repo,
        SiteContext $siteContext,
    ): Response
    {
        $site = $siteContext->getSite();
        $status = $request->query->get('status'); // фильтр по статусу
        $search = $request->query->get('q');      // поиск по email
        $page = $request->query->getInt('page', 1);

        $qb = $repo->createQueryBuilder('s')
            ->where('s.site = :site')
            ->setParameter('site', $site)
            ->orderBy('s.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('s.status = :status')
               ->setParameter('status', $status);
        }

        if ($search) {
            $qb->andWhere('s.email LIKE :search')
               ->setParameter('search', "%{$search}%");
        }

        // Pagination (Pagerfanta или ручная)
        $perPage = 50;
        $total = (clone $qb)->select('COUNT(s.id)')->getQuery()->getSingleScalarResult();
        $subscribers = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        // Статистика
        $stats = $repo->getStatsBySite($site);
        // { total: 1234, active: 980, pending: 200, unsubscribed: 50, bounced: 4 }

        return $this->render('admin/newsletter/subscribers.html.twig', [
            'subscribers' => $subscribers,
            'stats' => $stats,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'status' => $status,
            'search' => $search,
        ]);
    }

    #[Route('/subscribers/export', name: 'admin_newsletter_export')]
    public function export(
        NewsletterSubscriberRepository $repo,
        SiteContext $siteContext,
    ): Response
    {
        $subscribers = $repo->findBy([
            'site' => $siteContext->getSite(),
            'status' => SubscriberStatus::ACTIVE,
        ]);

        $csv = "email,confirmed_at,source\n";
        foreach ($subscribers as $s) {
            $csv .= "{$s->getEmail()},{$s->getConfirmedAt()?->format('Y-m-d')},{$s->getSource()}\n";
        }

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="subscribers_' . date('Y-m-d') . '.csv"',
        ]);
    }
}
```

### Шаблон списка подписчиков

```twig
{# templates/admin/newsletter/subscribers.html.twig #}

{% extends 'admin/base.html.twig' %}

{% block body %}
<div class="admin-page">
    <div class="admin-header">
        <h1>Newsletter Subscribers</h1>
        <a href="{{ path('admin_newsletter_export') }}" class="admin-btn admin-btn-secondary">
            Export CSV
        </a>
    </div>

    {# Статистика #}
    <div class="stats-grid">
        <div class="stat-card">
            <span class="stat-value">{{ stats.active }}</span>
            <span class="stat-label">Active</span>
        </div>
        <div class="stat-card">
            <span class="stat-value">{{ stats.pending }}</span>
            <span class="stat-label">Pending</span>
        </div>
        <div class="stat-card">
            <span class="stat-value">{{ stats.unsubscribed }}</span>
            <span class="stat-label">Unsubscribed</span>
        </div>
        <div class="stat-card">
            <span class="stat-value">{{ stats.total }}</span>
            <span class="stat-label">Total</span>
        </div>
    </div>

    {# Фильтры #}
    <div class="admin-filters">
        <input type="search" name="q" value="{{ search }}" 
               placeholder="Search by email..." class="admin-input">
        <select name="status" class="admin-select">
            <option value="">All statuses</option>
            <option value="active" {{ status == 'active' ? 'selected' }}>Active</option>
            <option value="pending" {{ status == 'pending' ? 'selected' }}>Pending</option>
            <option value="unsubscribed" {{ status == 'unsubscribed' ? 'selected' }}>Unsubscribed</option>
            <option value="bounced" {{ status == 'bounced' ? 'selected' }}>Bounced</option>
        </select>
    </div>

    {# Таблица #}
    <table class="admin-table">
        <thead>
            <tr>
                <th>Email</th>
                <th>Status</th>
                <th>Source</th>
                <th>Subscribed</th>
                <th>Confirmed</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            {% for sub in subscribers %}
            <tr>
                <td>{{ sub.email }}</td>
                <td>
                    <span class="status-badge status-{{ sub.status.value }}">
                        {{ sub.status.value }}
                    </span>
                </td>
                <td>{{ sub.source }}</td>
                <td>{{ sub.createdAt|date('M d, Y') }}</td>
                <td>{{ sub.confirmedAt ? sub.confirmedAt|date('M d, Y') : '—' }}</td>
                <td>
                    {% if sub.status == constant('App\\Entity\\Enum\\SubscriberStatus::ACTIVE') %}
                        <button class="admin-btn-sm admin-btn-danger"
                                onclick="if(confirm('Unsubscribe this email?')) window.location='{{ path('admin_newsletter_unsubscribe', {id: sub.id}) }}'">
                            Unsubscribe
                        </button>
                    {% endif %}
                </td>
            </tr>
            {% endfor %}
        </tbody>
    </table>

    {# Pagination #}
    {% if total > perPage %}
    <nav class="admin-pagination">
        {% for p in 1..(total / perPage)|round(0, 'ceil') %}
            <a href="?page={{ p }}&status={{ status }}&q={{ search }}"
               class="{{ p == page ? 'active' : '' }}">{{ p }}</a>
        {% endfor %}
    </nav>
    {% endif %}
</div>
{% endblock %}
```

---

## Админка: генерация и редактирование дайджеста

### Генерация дайджеста (AI)

```php
// src/Service/Newsletter/DigestGenerator.php

class DigestGenerator
{
    public function __construct(
        private ArticleRepository $articleRepo,
        private CoinGeckoClient $coinGecko,
        private SiteContext $siteContext,
        private ClaudeClient $claude,          // обёртка над Claude API
        private Environment $twig,
    ) {}

    public function generate(): NewsletterIssue
    {
        $site = $this->siteContext->getSite();

        // 1. Собрать данные
        $topArticles = $this->articleRepo->findPublishedSince(
            site: $site,
            since: new \DateTimeImmutable('-24 hours'),
            limit: 10,
        );

        $btcPrice = $this->coinGecko->getPrice('bitcoin', 'usd');
        $ethPrice = $this->coinGecko->getPrice('ethereum', 'usd');
        $fearGreed = $this->getFearGreedIndex();

        // 2. Сгенерировать текст через Claude
        $articlesContext = array_map(fn($a) => [
            'title' => $a->getTitle(),
            'excerpt' => $a->getExcerpt(),
            'category' => $a->getCategory()->getName(),
            'url' => "https://{$site->getDomain()}/{$a->getCategory()->getSlug()}/{$a->getSlug()}",
        ], $topArticles);

        $prompt = $this->buildPrompt($articlesContext, $btcPrice, $ethPrice, $fearGreed);
        $aiResponse = $this->claude->complete($prompt);

        // 3. Парсить ответ AI
        $parsed = json_decode($aiResponse, true);

        // 4. Собрать HTML из шаблона
        $contentHtml = $this->twig->render('email/newsletter_digest.html.twig', [
            'site' => $site,
            'subject' => $parsed['subject'],
            'intro' => $parsed['intro'],
            'marketBrief' => $parsed['marketBrief'],
            'articles' => $topArticles,
            'articleSummaries' => $parsed['articleSummaries'] ?? [],
            'btcPrice' => $btcPrice,
            'ethPrice' => $ethPrice,
            'fearGreed' => $fearGreed,
        ]);

        // 5. Создать issue
        $issue = new NewsletterIssue();
        $issue->setSite($site);
        $issue->setSubject($parsed['subject']);
        $issue->setPreviewText($parsed['previewText'] ?? null);
        $issue->setContentHtml($contentHtml);
        $issue->setContentJson([
            'intro' => $parsed['intro'],
            'marketBrief' => $parsed['marketBrief'],
            'articleSummaries' => $parsed['articleSummaries'] ?? [],
            'articles' => $articlesContext,
            'btcPrice' => $btcPrice,
            'ethPrice' => $ethPrice,
            'fearGreed' => $fearGreed,
        ]);
        $issue->setGeneratedBy('ai');
        $issue->setStatus(IssueStatus::DRAFT);

        return $issue;
    }

    private function buildPrompt(array $articles, float $btcPrice, float $ethPrice, int $fearGreed): string
    {
        $articlesList = '';
        foreach ($articles as $a) {
            $articlesList .= "- [{$a['category']}] {$a['title']}: {$a['excerpt']}\n";
        }

        return <<<PROMPT
        You are writing the "Daily Crypto Brief" newsletter for a crypto news site.
        
        Market data:
        - BTC: \${$btcPrice}
        - ETH: \${$ethPrice}
        - Fear & Greed Index: {$fearGreed}/100
        
        Top articles from the last 24 hours:
        {$articlesList}
        
        Write a newsletter issue. Return JSON only:
        {
          "subject": "Email subject line, max 60 chars, include a key number or hook",
          "previewText": "Preview text for inbox, max 100 chars",
          "intro": "2-3 sentence intro paragraph summarizing the day in crypto",
          "marketBrief": "2-3 sentences on market conditions based on the prices and fear/greed",
          "articleSummaries": [
            {"title": "...", "summary": "1-2 sentence summary for the newsletter"}
          ]
        }
        
        Be concise, factual, no financial advice. Engaging but professional tone.
        PROMPT;
    }
}
```

### Email-шаблон дайджеста

```twig
{# templates/email/newsletter_digest.html.twig #}

{# 
  Inline CSS — email-клиенты не поддерживают <style> блоки надёжно.
  Максимальная ширина 600px — стандарт для email.
  Таблицы для layout — email-клиенты плохо поддерживают flexbox/grid.
#}

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ subject }}</title>
</head>
<body style="margin:0; padding:0; background:#f4f5f7; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
    
    {# Preview text (скрытый, виден только в inbox) #}
    {% if previewText %}
    <div style="display:none; max-height:0; overflow:hidden;">
        {{ previewText }}
        {{ '&nbsp;'|repeat(80) }}
    </div>
    {% endif %}

    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;">
        <tr><td align="center" style="padding: 24px 16px;">
            <table width="600" cellpadding="0" cellspacing="0" 
                   style="background:#ffffff; border-radius:8px; overflow:hidden;">
                
                {# Header #}
                <tr>
                    <td style="background:#1a1d23; padding:20px 32px;">
                        <h1 style="margin:0; color:#fff; font-size:20px; font-weight:700;">
                            {{ site.name }}
                        </h1>
                        <p style="margin:4px 0 0; color:#9ca3af; font-size:13px;">
                            Daily Crypto Brief · {{ 'now'|date('F j, Y') }}
                        </p>
                    </td>
                </tr>

                {# Market snapshot #}
                <tr>
                    <td style="padding:24px 32px; border-bottom:1px solid #e5e7eb;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="text-align:center; padding:8px;">
                                    <div style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em;">Bitcoin</div>
                                    <div style="font-size:20px; font-weight:700; color:#1a1d23;">${{ btcPrice|number_format(0) }}</div>
                                </td>
                                <td style="text-align:center; padding:8px;">
                                    <div style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em;">Ethereum</div>
                                    <div style="font-size:20px; font-weight:700; color:#1a1d23;">${{ ethPrice|number_format(0) }}</div>
                                </td>
                                <td style="text-align:center; padding:8px;">
                                    <div style="font-size:12px; color:#6b7280; text-transform:uppercase; letter-spacing:0.05em;">Fear & Greed</div>
                                    <div style="font-size:20px; font-weight:700; color:#1a1d23;">{{ fearGreed }}/100</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {# Intro #}
                <tr>
                    <td style="padding:24px 32px 8px;">
                        <p style="margin:0; font-size:16px; line-height:1.6; color:#374151;">
                            {{ intro }}
                        </p>
                    </td>
                </tr>

                {# Market brief #}
                <tr>
                    <td style="padding:8px 32px 24px;">
                        <p style="margin:0; font-size:15px; line-height:1.6; color:#6b7280;">
                            {{ marketBrief }}
                        </p>
                    </td>
                </tr>

                {# Articles #}
                <tr>
                    <td style="padding:0 32px 24px;">
                        <h2 style="margin:0 0 16px; font-size:14px; color:#6b7280; text-transform:uppercase; letter-spacing:0.06em; border-bottom:1px solid #e5e7eb; padding-bottom:8px;">
                            Today's Top Stories
                        </h2>
                        
                        {% for article in articles[:5] %}
                        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
                            <tr>
                                <td>
                                    <span style="font-size:11px; color:#2563eb; font-weight:600; text-transform:uppercase; letter-spacing:0.04em;">
                                        {{ article.category.name }}
                                    </span>
                                    <h3 style="margin:4px 0 6px; font-size:17px; line-height:1.3;">
                                        <a href="https://{{ site.domain }}/{{ article.category.slug }}/{{ article.slug }}" 
                                           style="color:#1a1d23; text-decoration:none;">
                                            {{ article.title }}
                                        </a>
                                    </h3>
                                    {% if articleSummaries[loop.index0] is defined %}
                                    <p style="margin:0; font-size:14px; line-height:1.5; color:#6b7280;">
                                        {{ articleSummaries[loop.index0].summary }}
                                    </p>
                                    {% endif %}
                                </td>
                            </tr>
                        </table>
                        {% endfor %}
                    </td>
                </tr>

                {# CTA #}
                <tr>
                    <td style="padding:0 32px 32px; text-align:center;">
                        <a href="https://{{ site.domain }}" 
                           style="display:inline-block; background:#2563eb; color:#fff; padding:12px 32px; border-radius:6px; text-decoration:none; font-weight:600; font-size:14px;">
                            Read More on {{ site.name }}
                        </a>
                    </td>
                </tr>

                {# Footer #}
                <tr>
                    <td style="background:#f9fafb; padding:20px 32px; border-top:1px solid #e5e7eb;">
                        <p style="margin:0; font-size:12px; color:#9ca3af; line-height:1.5; text-align:center;">
                            You're receiving this because you subscribed to {{ site.name }} Daily Crypto Brief.<br>
                            <a href="{{UNSUBSCRIBE_URL}}" style="color:#6b7280;">Unsubscribe</a> · 
                            <a href="https://{{ site.domain }}" style="color:#6b7280;">Visit {{ site.name }}</a>
                        </p>
                    </td>
                </tr>

            </table>
        </td></tr>
    </table>
</body>
</html>
```

### Админка: редактирование и отправка

```php
// src/Controller/Admin/NewsletterAdminController.php (продолжение)

#[Route('/issues', name: 'admin_newsletter_issues')]
public function issues(NewsletterIssueRepository $repo, SiteContext $siteContext): Response
{
    $issues = $repo->findBy(
        ['site' => $siteContext->getSite()],
        ['createdAt' => 'DESC'],
    );

    return $this->render('admin/newsletter/issues.html.twig', [
        'issues' => $issues,
    ]);
}

#[Route('/issues/generate', name: 'admin_newsletter_generate', methods: ['POST'])]
public function generate(
    DigestGenerator $generator,
    EntityManagerInterface $em,
): Response
{
    $issue = $generator->generate();
    $em->persist($issue);
    $em->flush();

    $this->addFlash('success', 'Digest generated. Review and edit before sending.');
    return $this->redirectToRoute('admin_newsletter_edit', ['id' => $issue->getId()]);
}

#[Route('/issues/{id}/edit', name: 'admin_newsletter_edit')]
public function edit(
    NewsletterIssue $issue,
    Request $request,
    EntityManagerInterface $em,
): Response
{
    if ($request->isMethod('POST')) {
        $issue->setSubject($request->request->get('subject'));
        $issue->setPreviewText($request->request->get('preview_text'));
        $issue->setContentHtml($request->request->get('content_html'));
        $issue->setStatus(IssueStatus::READY);
        $issue->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', 'Issue saved and marked as ready.');
        return $this->redirectToRoute('admin_newsletter_edit', ['id' => $issue->getId()]);
    }

    return $this->render('admin/newsletter/edit.html.twig', [
        'issue' => $issue,
    ]);
}

#[Route('/issues/{id}/preview', name: 'admin_newsletter_preview')]
public function preview(NewsletterIssue $issue): Response
{
    // Рендерить HTML письма как обычную страницу (для превью)
    $html = str_replace('{{UNSUBSCRIBE_URL}}', '#unsubscribe-preview', $issue->getContentHtml());
    return new Response($html);
}

#[Route('/issues/{id}/send-test', name: 'admin_newsletter_send_test', methods: ['POST'])]
public function sendTest(
    NewsletterIssue $issue,
    Request $request,
    NewsletterMailer $mailer,
): JsonResponse
{
    $testEmail = $request->request->get('email');

    // Создать временный subscriber для теста
    $testSubscriber = new NewsletterSubscriber();
    $testSubscriber->setEmail($testEmail);
    // Не сохраняем в БД

    try {
        $mailer->sendIssue($issue, $testSubscriber);
        return $this->json(['message' => "Test sent to {$testEmail}"]);
    } catch (\Exception $e) {
        return $this->json(['error' => $e->getMessage()], 500);
    }
}

#[Route('/issues/{id}/send', name: 'admin_newsletter_send', methods: ['POST'])]
public function send(
    NewsletterIssue $issue,
    NewsletterSendService $sendService,
): Response
{
    if ($issue->getStatus() !== IssueStatus::READY) {
        $this->addFlash('error', 'Issue must be in "ready" status to send.');
        return $this->redirectToRoute('admin_newsletter_edit', ['id' => $issue->getId()]);
    }

    $sendService->dispatch($issue);

    $this->addFlash('success', 'Sending started. Check progress on the issues list.');
    return $this->redirectToRoute('admin_newsletter_issues');
}
```

### Шаблон редактора

```twig
{# templates/admin/newsletter/edit.html.twig #}

{% extends 'admin/base.html.twig' %}

{% block body %}
<div class="admin-page">
    <div class="admin-header">
        <h1>Edit Newsletter Issue #{{ issue.id }}</h1>
        <div class="admin-header-actions">
            <span class="status-badge status-{{ issue.status.value }}">
                {{ issue.status.value }}
            </span>
        </div>
    </div>

    <div class="newsletter-editor-layout">
        {# Форма редактирования #}
        <div class="newsletter-editor-form">
            <form method="post" id="issue-form">
                <div class="admin-field">
                    <label class="admin-label">Subject</label>
                    <input type="text" name="subject" value="{{ issue.subject }}" 
                           class="admin-input" maxlength="255" required>
                    <span class="admin-hint">{{ issue.subject|length }}/255 characters</span>
                </div>

                <div class="admin-field">
                    <label class="admin-label">Preview Text (visible in inbox)</label>
                    <input type="text" name="preview_text" value="{{ issue.previewText }}" 
                           class="admin-input" maxlength="255">
                    <span class="admin-hint">Shows after subject in most email clients</span>
                </div>

                <div class="admin-field">
                    <label class="admin-label">HTML Content</label>
                    <textarea name="content_html" class="admin-textarea" 
                              rows="30" id="content-editor">{{ issue.contentHtml }}</textarea>
                </div>

                <div class="admin-actions">
                    <button type="submit" class="admin-btn admin-btn-primary">
                        Save as Ready
                    </button>
                </div>
            </form>

            {# Тестовая отправка #}
            <div class="admin-section" style="margin-top: var(--space-8);">
                <h3>Test Send</h3>
                <div style="display: flex; gap: var(--space-2);">
                    <input type="email" id="test-email" placeholder="your@email.com" class="admin-input">
                    <button id="send-test-btn" class="admin-btn admin-btn-secondary">
                        Send Test
                    </button>
                </div>
                <div id="test-result" style="margin-top: var(--space-2);"></div>
            </div>

            {# Кнопка отправки всем #}
            {% if issue.status.value == 'ready' %}
            <div class="admin-section" style="margin-top: var(--space-8); padding: var(--space-5); background: var(--color-warning-bg); border-radius: var(--radius-md);">
                <h3>Send to All Subscribers</h3>
                <p>This will send the newsletter to all <strong>active</strong> subscribers.</p>
                <form method="post" action="{{ path('admin_newsletter_send', {id: issue.id}) }}"
                      onsubmit="return confirm('Send this issue to all active subscribers? This cannot be undone.');">
                    <button type="submit" class="admin-btn admin-btn-danger">
                        Send Newsletter
                    </button>
                </form>
            </div>
            {% endif %}
        </div>

        {# Превью #}
        <div class="newsletter-editor-preview">
            <div class="preview-header">
                <h3>Preview</h3>
                <a href="{{ path('admin_newsletter_preview', {id: issue.id}) }}" 
                   target="_blank" class="admin-link">Open in new tab ↗</a>
            </div>
            <iframe id="preview-iframe" 
                    src="{{ path('admin_newsletter_preview', {id: issue.id}) }}"
                    style="width:100%; height:800px; border:1px solid var(--color-border); border-radius: var(--radius-md);">
            </iframe>
        </div>
    </div>
</div>

<script>
// Тестовая отправка
document.getElementById('send-test-btn')?.addEventListener('click', async () => {
    const email = document.getElementById('test-email').value;
    const result = document.getElementById('test-result');
    
    if (!email) return;
    
    const resp = await fetch('{{ path('admin_newsletter_send_test', {id: issue.id}) }}', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `email=${encodeURIComponent(email)}`,
    });
    
    const data = await resp.json();
    result.textContent = data.message || data.error;
    result.style.color = resp.ok ? 'var(--color-positive)' : 'var(--color-negative)';
});
</script>
{% endblock %}
```

```css
.newsletter-editor-layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--space-6);
}

@media (min-width: 1200px) {
    .newsletter-editor-layout {
        grid-template-columns: 1fr 1fr;
    }
}
```

---

## Batch-отправка через Messenger

```php
// src/Service/Newsletter/NewsletterSendService.php

class NewsletterSendService
{
    public function __construct(
        private NewsletterSubscriberRepository $subscriberRepo,
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
    ) {}

    public function dispatch(NewsletterIssue $issue): void
    {
        $site = $issue->getSite();
        $subscribers = $this->subscriberRepo->findBy([
            'site' => $site,
            'status' => SubscriberStatus::ACTIVE,
        ]);

        $issue->setStatus(IssueStatus::SENDING);
        $issue->setRecipientsCount(count($subscribers));
        $issue->setSentAt(new \DateTimeImmutable());
        $this->em->flush();

        // Создать записи в send_log и dispatch сообщения
        foreach ($subscribers as $subscriber) {
            $log = new NewsletterSendLog();
            $log->setIssue($issue);
            $log->setSubscriber($subscriber);
            $log->setStatus('queued');
            $this->em->persist($log);

            // Dispatch в очередь (Symfony Messenger)
            $this->bus->dispatch(new SendNewsletterMessage(
                issueId: $issue->getId(),
                subscriberId: $subscriber->getId(),
            ));
        }

        $this->em->flush();
    }
}
```

```php
// src/Message/SendNewsletterMessage.php

final readonly class SendNewsletterMessage
{
    public function __construct(
        public int $issueId,
        public int $subscriberId,
    ) {}
}
```

```php
// src/MessageHandler/SendNewsletterHandler.php

#[AsMessageHandler]
class SendNewsletterHandler
{
    public function __construct(
        private NewsletterIssueRepository $issueRepo,
        private NewsletterSubscriberRepository $subscriberRepo,
        private NewsletterSendLogRepository $logRepo,
        private NewsletterMailer $mailer,
        private EntityManagerInterface $em,
    ) {}

    public function __invoke(SendNewsletterMessage $message): void
    {
        $issue = $this->issueRepo->find($message->issueId);
        $subscriber = $this->subscriberRepo->find($message->subscriberId);

        if (!$issue || !$subscriber) return;
        if ($subscriber->getStatus() !== SubscriberStatus::ACTIVE) return;

        $log = $this->logRepo->findOneBy([
            'issue' => $issue,
            'subscriber' => $subscriber,
        ]);

        try {
            $this->mailer->sendIssue($issue, $subscriber);
            $log?->setStatus('sent');
            $log?->setSentAt(new \DateTimeImmutable());
            $issue->setSentCount($issue->getSentCount() + 1);
        } catch (\Exception $e) {
            $log?->setStatus('failed');
            $log?->setErrorMessage($e->getMessage());
            $issue->setFailedCount($issue->getFailedCount() + 1);

            // Пометить subscriber как bounced при определённых ошибках
            if ($this->isBounce($e)) {
                $subscriber->setStatus(SubscriberStatus::BOUNCED);
            }
        }

        $this->em->flush();

        // Проверить, завершена ли рассылка
        $totalProcessed = $issue->getSentCount() + $issue->getFailedCount();
        if ($totalProcessed >= $issue->getRecipientsCount()) {
            $issue->setStatus(IssueStatus::SENT);
            $this->em->flush();
        }
    }

    private function isBounce(\Exception $e): bool
    {
        $message = $e->getMessage();
        return str_contains($message, 'Mailbox not found')
            || str_contains($message, 'User unknown')
            || str_contains($message, '550');
    }
}
```

### Messenger config

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            newsletter:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    queue_name: newsletter
                retry_strategy:
                    max_retries: 2
                    delay: 5000         # 5 сек между retry
                    multiplier: 3       # 5s → 15s → 45s

        routing:
            'App\Message\SendNewsletterMessage': newsletter
```

### Mailer config (SMTP)

```yaml
# config/packages/mailer.yaml
# Для MVP: обычный SMTP (Mailgun, Postmark, Amazon SES, или даже Gmail SMTP)
# Для production: Postmark или Amazon SES (дешевле при объёмах)

framework:
    mailer:
        dsn: '%env(MAILER_DSN)%'
        # Примеры:
        # smtp://user:pass@smtp.mailgun.org:587
        # postmark+api://KEY@default
        # ses+smtp://ACCESS_KEY:SECRET_KEY@default?region=eu-west-1
```

```env
# .env.local
MAILER_DSN=smtp://user:password@smtp.mailgun.org:587
MESSENGER_TRANSPORT_DSN=doctrine://default?queue_name=newsletter
```

---

## Rate limiting отправки

Чтобы не попасть под лимиты SMTP-провайдера, добавить задержку между сообщениями:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            newsletter:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    queue_name: newsletter
```

```php
// В SendNewsletterHandler — добавить sleep между отправками
// Или использовать Symfony RateLimiter:

// Лучший подход: rate limiter в handler
#[AsMessageHandler]
class SendNewsletterHandler
{
    public function __construct(
        // ...
        private RateLimiterFactory $newsletterSendLimiter,
    ) {}

    public function __invoke(SendNewsletterMessage $message): void
    {
        // Ждать, если превышен лимит (10 писем/сек)
        $limiter = $this->newsletterSendLimiter->create('newsletter_send');
        $limiter->reserve(1)->wait();

        // ... отправка
    }
}
```

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        newsletter_send:
            policy: token_bucket
            limit: 10          # 10 писем
            rate: { interval: '1 second' }
```

---

## Порядок реализации в Claude Code

```
Шаг 1:  Создай entities: NewsletterSubscriber, NewsletterIssue, NewsletterSendLog.
         Создай enums: SubscriberStatus, IssueStatus.
         Создай миграции. Проведи их.

Шаг 2:  Реализуй NewsletterController (frontend):
         - POST /newsletter/subscribe (JSON)
         - GET /newsletter/confirm/{token}
         - GET /newsletter/unsubscribe/{token}
         Шаблоны: confirmed.html.twig, unsubscribed.html.twig.

Шаг 3:  Реализуй NewsletterMailer сервис.
         Шаблон confirmation email.
         Настрой mailer в config (для dev — MAILER_DSN=null://null логирует в profiler).

Шаг 4:  Подключи JS newsletter.js к формам подписки на главной и в footer.
         Проверь: ввод email → POST → "Check email" сообщение.
         Проверь: повторная подписка → корректная ошибка.

Шаг 5:  Админка: список подписчиков.
         Фильтры по status и поиск по email.
         Экспорт CSV. Ручная отписка.

Шаг 6:  DigestGenerator сервис.
         Промпт для Claude API. Парсинг JSON-ответа.
         Шаблон email/newsletter_digest.html.twig (inline CSS, таблицы).
         
Шаг 7:  Админка: список issues + кнопка "Generate Digest".
         Редактор: subject, preview_text, content_html (textarea).
         Iframe preview рядом с редактором.
         Send Test — отправка на один email.

Шаг 8:  NewsletterSendService + Messenger.
         SendNewsletterMessage + SendNewsletterHandler.
         Rate limiter (10 писем/сек).
         Конфиг messenger.yaml с doctrine transport.

Шаг 9:  Кнопка "Send Newsletter" на странице редактирования issue.
         Confirm dialog. Запуск batch-рассылки.
         Обновление статусов issue (sending → sent).

Шаг 10: Consumer для очереди:
         docker compose exec php bin/console messenger:consume newsletter
         Добавить в docker-compose как отдельный сервис или supervisor.

Шаг 11: Проверка полного цикла:
         Подписка → confirmation email → confirm → 
         Generate digest → Edit → Send test → Send to all →
         Проверить письмо → Unsubscribe.
```
