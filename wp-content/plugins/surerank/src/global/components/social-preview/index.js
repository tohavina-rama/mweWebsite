import { __ } from '@wordpress/i18n';
import { Image, User } from 'lucide-react';
import { Avatar } from '@bsf/force-ui';
import { cn, decodeHtmlEntities } from '@Functions/utils';
import GlobalRemoveButton from '@AdminComponents/global-remove-image-button';

const getDateString = ( type ) => {
	const date = new Date();
	const month = date.toLocaleString( 'default', { month: 'long' } );
	const day = date.getDate();
	const year = date.getFullYear().toString().slice( -2 );

	if ( 'twitter' === type ) {
		return `${ day } ${ month } ${ year }`;
	}

	return `${ month } ${ day }`;
};

const ImagePlaceholder = ( { className, ...props } ) => (
	<div
		className={ cn(
			'w-full h-full bg-background-secondary flex items-center justify-center',
			className
		) }
		{ ...props }
	>
		<Image className="size-6 m-auto" />
	</div>
);

const SocialPreview = ( {
	displayName = 'Name',
	username = '@username',
	type = 'facebook',
	title = __( 'Sample Post - Testing Site', 'surerank' ),
	description = '',
	imageURL = '',
	siteURL = 'surerank.com',
	twitterLargePreview = false,
	onClickRemove,
	hideRemoveButton = true,
} ) => {
	let designContent = null;
	const decoded_description = decodeHtmlEntities( description );
	const decoded_title = decodeHtmlEntities( title );
	const descriptionContent = decoded_description || '';

	switch ( type ) {
		case 'twitter':
			designContent = (
				<div className={ cn( ! twitterLargePreview && 'p-4' ) }>
					{ /* Header */ }
					<div
						className={ cn(
							'flex gap-3 justify-start items-center',
							twitterLargePreview ? 'p-3' : 'pb-4'
						) }
					>
						<Avatar size="md" variant="gray">
							<User />
						</Avatar>

						<div className="inline-flex items-center gap-1">
							<p className="m-0 font-medium text-base leading-6">
								{ displayName }
							</p>
							<p className="m-0 text-base leading-4 font-normal text-text-secondary">
								{ username } . { getDateString( 'twitter' ) }
							</p>
						</div>
					</div>
					<div
						className={ cn(
							'grid overflow-hidden',
							twitterLargePreview
								? 'grid-rows-[16.8125rem_1fr]'
								: 'min-h-[7.75rem] grid-cols-[7.5rem_1fr] rounded-2xl border border-solid border-border-subtle'
						) }
					>
						{ imageURL ? (
							<div
								className={ cn(
									'relative w-full h-full',
									! twitterLargePreview && 'inline-flex'
								) }
							>
								<img
									className={ cn(
										'w-full h-full object-cover m-0',
										! twitterLargePreview &&
											'max-h-[7.625rem] border-y-0 border-l-0 border-r border-solid border-border-subtle'
									) }
									src={ imageURL }
									alt="thumbnail"
								/>
								{ ! hideRemoveButton && (
									<GlobalRemoveButton
										onClick={ onClickRemove }
									/>
								) }
							</div>
						) : (
							<ImagePlaceholder
								className={ cn(
									! twitterLargePreview &&
										'border-y-0 border-l-0 border-r border-solid border-border-subtle'
								) }
							/>
						) }
						<div
							className={ cn(
								'inline-grid items-center justify-start gap-0.5',
								twitterLargePreview ? 'p-3' : 'px-3 py-5'
							) }
						>
							<p
								className={ cn(
									'm-0 font-normal text-text-secondary leading-4',
									twitterLargePreview
										? 'text-[0.8125rem]'
										: 'text-xs'
								) }
							>
								{ siteURL }
							</p>
							<div className="w-full overflow-hidden">
								<p
									className={ cn(
										'm-0 text-[0.9375rem] font-semibold text-text-primary',
										twitterLargePreview
											? 'line-clamp-2 leading-6'
											: 'whitespace-nowrap leading-5'
									) }
								>
									{ decoded_title }
								</p>
							</div>
							<p
								className={ cn(
									'm-0 font-normal text-text-secondary line-clamp-3 leading-5',
									twitterLargePreview
										? 'text-[0.9375rem]'
										: 'text-sm'
								) }
							>
								{ descriptionContent }
							</p>
						</div>
					</div>
				</div>
			);
			break;
		case 'facebook':
			designContent = (
				<>
					{ /* Image */ }
					<div className="w-full h-[16.8125rem] overflow-clip">
						{ imageURL ? (
							<div className="relative w-full h-full">
								<img
									src={ imageURL }
									alt="Social Post"
									className="w-full h-full object-cover"
								/>
								{ ! hideRemoveButton && (
									<GlobalRemoveButton
										onClick={ onClickRemove }
									/>
								) }
							</div>
						) : (
							<ImagePlaceholder />
						) }
					</div>

					{ /* Footer */ }
					<div className="p-3 w-full">
						<p className="m-0 text-xs leading-4 font-normal text-text-secondary">
							{ siteURL }
						</p>
						<p className="mt-1.5 mb-1 text-base leading-6 font-semibold text-text-primary line-clamp-2">
							{ decoded_title }
						</p>
						<p className="m-0 text-sm leading-5 font-normal line-clamp-2">
							{ descriptionContent }
						</p>
					</div>
				</>
			);
			break;
		default:
			designContent = null;
			break;
	}

	return (
		<div className="p-2 rounded-lg bg-background-secondary">
			<div className="flex flex-col rounded-md border border-solid border-border-subtle shadow-sm overflow-hidden bg-background-primary">
				{ designContent }
			</div>
		</div>
	);
};

export default SocialPreview;
