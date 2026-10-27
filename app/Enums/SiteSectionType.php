<?php

namespace App\Enums;

use App\Cms\Sections\BenefitsSection;
use App\Cms\Sections\CategoriesSection;
use App\Cms\Sections\CoverageSection;
use App\Cms\Sections\FaqSection;
use App\Cms\Sections\FeaturedProjectsSection;
use App\Cms\Sections\FinalCtaSection;
use App\Cms\Sections\HeroSection;
use App\Cms\Sections\HomeVisitSection;
use App\Cms\Sections\PaymentOptionsSection;
use App\Cms\Sections\ProcessSection;
use App\Cms\Sections\SectionSchema;
use App\Cms\Sections\TestimonialsSection;

/**
 * Tipos de sección de la portada. Cada tipo tiene una clase de esquema en
 * App\Cms\Sections que define campos, valores por defecto y validación.
 */
enum SiteSectionType: string
{
    case Hero = 'hero';
    case Categories = 'categories';
    case FeaturedProjects = 'featured_projects';
    case Process = 'process';
    case Benefits = 'benefits';
    case PaymentOptions = 'payment_options';
    case HomeVisit = 'home_visit';
    case Coverage = 'coverage';
    case Testimonials = 'testimonials';
    case Faq = 'faq';
    case FinalCta = 'final_cta';

    public function label(): string
    {
        return $this->schema()->label();
    }

    public function schema(): SectionSchema
    {
        return match ($this) {
            self::Hero => new HeroSection,
            self::Categories => new CategoriesSection,
            self::FeaturedProjects => new FeaturedProjectsSection,
            self::Process => new ProcessSection,
            self::Benefits => new BenefitsSection,
            self::PaymentOptions => new PaymentOptionsSection,
            self::HomeVisit => new HomeVisitSection,
            self::Coverage => new CoverageSection,
            self::Testimonials => new TestimonialsSection,
            self::Faq => new FaqSection,
            self::FinalCta => new FinalCtaSection,
        };
    }
}
