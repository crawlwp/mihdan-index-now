<?php

namespace Mihdan\IndexNow\SEOCore\Importer;

/**
 * Converts title/description template tokens from other SEO plugins into
 * CrawlWP's {{ namespaced.token }} syntax.
 *
 * Pure PHP — no WordPress dependency — so it can be unit-tested in isolation.
 */
class TokenMapper
{
	/**
	 * Replace foreign template tokens in $text with CrawlWP equivalents.
	 *
	 * Unknown tokens are left untouched so editors can still see them.
	 */
	public static function convert(string $text, string $source): string
	{
		if ($text === '') {
			return '';
		}

		$map = self::map_for($source);

		if ($map === []) {
			return $text;
		}

		return strtr($text, $map);
	}

	/**
	 * @return array<string,string>
	 */
	public static function map_for(string $source): array
	{
		switch ($source) {
			case 'yoast':
				return self::yoast_map();
			case 'rankmath':
				return self::rank_math_map();
			case 'aioseo':
				return self::aioseo_map();
			case 'seopress':
				return self::seopress_map();
			case 'slimseo':
				return self::slim_seo_map();
			case 'tsf':
				return [];
			default:
				return [];
		}
	}

	/**
	 * @return array<string,string>
	 */
	private static function yoast_map(): array
	{
		return [
			'%%title%%'                => '{{ post.title }}',
			'%%excerpt%%'              => '{{ post.auto_description }}',
			'%%excerpt_only%%'         => '{{ post.excerpt }}',
			'%%date%%'                 => '{{ post.date }}',
			'%%modified%%'             => '{{ post.modified }}',
			'%%name%%'                 => '{{ post.author }}',
			'%%user_description%%'     => '{{ author.description }}',
			'%%userid%%'               => '{{ post.author }}',
			'%%currentyear%%'          => '{{ current.year }}',
			'%%currentmonth%%'         => '{{ current.month }}',
			'%%currentday%%'           => '{{ current.day }}',
			'%%sitename%%'             => '{{ site.title }}',
			'%%sitedesc%%'             => '{{ site.description }}',
			'%%sep%%'                  => '{{ sep }}',
			'%%page%%'                 => '{{ page }}',
			'%%pagetotal%%'            => '{{ page }}',
			'%%pagenumber%%'           => '{{ page }}',
			'%%term_title%%'           => '{{ term.name }}',
			'%%term_description%%'     => '{{ term.description }}',
			'%%category%%'             => '{{ post.category }}',
			'%%category_description%%' => '{{ term.description }}',
			'%%tag%%'                  => '{{ post.tag }}',
			'%%tag_description%%'      => '{{ term.description }}',
			'%%searchphrase%%'         => '{{ search.query }}',
			'%%pt_single%%'            => '{{ post_type.singular }}',
			'%%pt_plural%%'            => '{{ post_type.plural }}',
			'%%archive_title%%'        => '{{ post_type.plural }}',
			'%%parent_title%%'         => '{{ post.title }}',
			'%%caption%%'              => '{{ post.auto_description }}',
			'%%id%%'                   => '{{ post.id }}',
			'%%permalink%%'            => '{{ post.url }}',
			'%%focuskw%%'              => '',
			'%%term404%%'              => '',
			'%%ct_desc_category%%'     => '{{ term.description }}',
		];
	}

	/**
	 * @return array<string,string>
	 */
	private static function rank_math_map(): array
	{
		return [
			'%title%'             => '{{ post.title }}',
			'%seo_title%'         => '{{ post.title }}',
			'%excerpt%'           => '{{ post.auto_description }}',
			'%excerpt_only%'      => '{{ post.excerpt }}',
			'%date%'              => '{{ post.date }}',
			'%modified%'          => '{{ post.modified }}',
			'%name%'              => '{{ post.author }}',
			'%user_description%'  => '{{ author.description }}',
			'%userid%'            => '{{ post.author }}',
			'%currentyear%'       => '{{ current.year }}',
			'%currentmonth%'      => '{{ current.month }}',
			'%currentday%'        => '{{ current.day }}',
			'%currentdate%'       => '{{ current.date }}',
			'%sitename%'          => '{{ site.title }}',
			'%sitedesc%'          => '{{ site.description }}',
			'%sep%'               => '{{ sep }}',
			'%page%'              => '{{ page }}',
			'%pagenumber%'        => '{{ page }}',
			'%pagetotal%'         => '{{ page }}',
			'%term%'              => '{{ term.name }}',
			'%term_title%'        => '{{ term.name }}',
			'%term_description%'  => '{{ term.description }}',
			'%category%'          => '{{ post.category }}',
			'%categories%'        => '{{ post.category }}',
			'%tag%'               => '{{ post.tag }}',
			'%tags%'              => '{{ post.tag }}',
			'%search_query%'      => '{{ search.query }}',
			'%pt_single%'         => '{{ post_type.singular }}',
			'%pt_plural%'         => '{{ post_type.plural }}',
			'%url%'               => '{{ post.url }}',
			'%id%'                => '{{ post.id }}',
			'%focuskw%'           => '',
			'%keywords%'          => '',
			'%org_name%'          => '{{ site.title }}',
			'%org_logo%'          => '',
			'%filename%'          => '{{ post.title }}',
			'%post_thumbnail%'    => '',
		];
	}

	/**
	 * @return array<string,string>
	 */
	private static function aioseo_map(): array
	{
		return [
			'#post_title'           => '{{ post.title }}',
			'#post_excerpt'         => '{{ post.auto_description }}',
			'#post_excerpt_only'    => '{{ post.excerpt }}',
			'#post_content'         => '{{ post.content }}',
			'#post_date'            => '{{ post.date }}',
			'#post_modified_date'   => '{{ post.modified }}',
			'#post_url'             => '{{ post.url }}',
			'#permalink'            => '{{ post.url }}',
			'#post_id'              => '{{ post.id }}',
			'#author_name'          => '{{ post.author }}',
			'#author_first_name'    => '{{ author.first_name }}',
			'#author_last_name'     => '{{ author.last_name }}',
			'#site_title'           => '{{ site.title }}',
			'#site_description'     => '{{ site.description }}',
			'#separator_sa'         => '{{ sep }}',
			'#separator'            => '{{ sep }}',
			'#current_year'         => '{{ current.year }}',
			'#current_month'        => '{{ current.month }}',
			'#current_day'          => '{{ current.day }}',
			'#current_date'         => '{{ current.date }}',
			'#categories'           => '{{ post.category }}',
			'#taxonomy_title'       => '{{ term.name }}',
			'#taxonomy_description' => '{{ term.description }}',
			'#search_term'          => '{{ search.query }}',
			'#archive_title'        => '{{ post_type.plural }}',
			'#page_number'          => '{{ page }}',
		];
	}

	/**
	 * @return array<string,string>
	 */
	private static function seopress_map(): array
	{
		return [
			'%%post_title%%'         => '{{ post.title }}',
			'%%post_excerpt%%'       => '{{ post.auto_description }}',
			'%%post_content%%'       => '{{ post.content }}',
			'%%post_date%%'          => '{{ post.date }}',
			'%%post_modified_date%%' => '{{ post.modified }}',
			'%%post_author%%'        => '{{ post.author }}',
			'%%post_category%%'      => '{{ post.category }}',
			'%%post_tag%%'           => '{{ post.tag }}',
			'%%post_url%%'           => '{{ post.url }}',
			'%%_category_title%%'    => '{{ post.category }}',
			'%%sitetitle%%'          => '{{ site.title }}',
			'%%tagline%%'            => '{{ site.description }}',
			'%%sitedesc%%'           => '{{ site.description }}',
			'%%sep%%'                => '{{ sep }}',
			'%%current_year%%'       => '{{ current.year }}',
			'%%current_month%%'      => '{{ current.month }}',
			'%%current_day%%'        => '{{ current.day }}',
			'%%current_date%%'       => '{{ current.date }}',
			'%%term_title%%'         => '{{ term.name }}',
			'%%term_description%%'   => '{{ term.description }}',
			'%%search_keywords%%'    => '{{ search.query }}',
			'%%page%%'               => '{{ page }}',
			'%%cpt_plural%%'         => '{{ post_type.plural }}',
			'%%archive_title%%'      => '{{ post_type.plural }}',
			'%%target_keyword%%'     => '',
		];
	}

	/**
	 * Slim SEO already uses a very similar {{ }} syntax; only flatten a few
	 * aliases onto CrawlWP's supported tokens.
	 *
	 * @return array<string,string>
	 */
	private static function slim_seo_map(): array
	{
		return [
			'{{ post.title }}'            => '{{ post.title }}',
			'{{ site.title }}'            => '{{ site.title }}',
			'{{ site.description }}'      => '{{ site.description }}',
			'{{ sep }}'                   => '{{ sep }}',
			'{{ post.auto_description }}' => '{{ post.auto_description }}',
			'{{ post.excerpt }}'          => '{{ post.excerpt }}',
			'{{ post.content }}'          => '{{ post.content }}',
			'{{ post.date }}'             => '{{ post.date }}',
			'{{ post.categories }}'       => '{{ post.category }}',
			'{{ post.tags }}'             => '{{ post.tag }}',
			'{{ term.name }}'             => '{{ term.name }}',
			'{{ term.description }}'      => '{{ term.description }}',
			'{{ term.auto_description }}' => '{{ term.auto_description }}',
			'{{ author.display_name }}'   => '{{ post.author }}',
			'{{ author.description }}'    => '{{ author.description }}',
			'{{ post_type.singular }}'    => '{{ post_type.singular }}',
			'{{ post_type.plural }}'      => '{{ post_type.plural }}',
			'{{ page }}'                  => '{{ page }}',
			'{{ current.year }}'          => '{{ current.year }}',
		];
	}
}
