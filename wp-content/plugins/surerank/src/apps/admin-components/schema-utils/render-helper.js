import { __ } from '@wordpress/i18n';
import { Select, EditorInput, Button, Label, Input, Text } from '@bsf/force-ui';
import { editorValueToString, stringValueToFormatJSON } from '@Functions/utils';
import { Trash, Plus, Info } from 'lucide-react';
import { generateUUID } from '@AdminComponents/schema-utils/utils';
import { SeoPopupTooltip } from '@AdminComponents/tooltip';

const WORD_BREAK_ALL_EDITOR_INPUT = [ 'url', 'logo' ];
const STYLES_OVERRIDE_FOR_EDITOR_INPUT = {
	wordBreak: 'break-all',
};

// Add the GroupFieldRenderer component
export const GroupFieldRenderer = ( {
	field,
	schemaType,
	getFieldValue,
	onFieldChange,
	variableSuggestions,
} ) => {
	if ( ! field.fields || field.fields.length === 0 ) {
		return null;
	}

	const groupType = field.fields.find( ( f ) => f.id === '@type' )
		? getFieldValue( '@type', field.id )
		: null;

	return (
		<div className="space-y-2 w-full border-l-2 border-gray-100 pt-2">
			{ field.fields.map( ( subField ) => {
				if ( subField.hidden || subField.type === 'Hidden' ) {
					return null;
				}

				if (
					subField.main &&
					groupType &&
					subField.main !== groupType
				) {
					return null;
				}

				return (
					<div key={ subField.id } className="space-y-1.5">
						<div className="flex items-center justify-start gap-1.5 w-full">
							<Label
								tag="span"
								size="sm"
								className="space-x-0.5"
								required={ subField.required }
							>
								<span>{ subField.label }</span>
							</Label>
							{ subField.tooltip && (
								<SeoPopupTooltip
									content={ subField.tooltip }
									placement="top"
									arrow
									className="z-[99999]"
								>
									<Info
										className="size-4 text-icon-secondary"
										title={ subField.tooltip }
									/>
								</SeoPopupTooltip>
							) }
						</div>
						<div className="flex items-center justify-start gap-1.5 w-full">
							{ renderFieldCommon( {
								field: subField,
								schemaType,
								getFieldValue: ( fieldId ) =>
									getFieldValue( fieldId, field.id ),
								onFieldChange: ( fieldId, value ) =>
									onFieldChange( fieldId, value, field.id ),
								variableSuggestions,
								renderAsGroupComponent: false,
							} ) }
						</div>
						{ subField.type !== 'Select' && (
							<Text size={ 14 } weight={ 400 } color="help">
								{ __(
									'Type @ to view variable suggestions',
									'surerank'
								) }
							</Text>
						) }
					</div>
				);
			} ) }
		</div>
	);
};

export const renderCloneableField = ( {
	field,
	getFieldValue,
	onFieldChange,
	variableSuggestions,
	placeholder = '',
} ) => {
	const existingValues = getFieldValue( field.id ) || {};

	if ( Object.keys( existingValues ).length === 0 ) {
		existingValues[ generateUUID( 7 ) ] = ''; // Ensure first key is unique
	}

	const handleAddNewField = () => {
		const newId = generateUUID( 7 );
		const updatedValues = {
			...existingValues,
			[ newId ]: '',
		};
		onFieldChange( field.id, updatedValues );
	};

	return (
		<div className="flex flex-col gap-2 w-full">
			{ Object.entries( existingValues ).map( ( [ key, value ] ) => (
				<div key={ key } className="flex items-center gap-1.5 w-full">
					<EditorInput
						by="label"
						trigger="@"
						options={ variableSuggestions }
						placeholder={ placeholder }
						defaultValue={ stringValueToFormatJSON(
							value,
							variableSuggestions,
							'value'
						) }
						onChange={ ( editorState ) => {
							onFieldChange( field.id, {
								...existingValues,
								[ key ]: editorValueToString(
									editorState.toJSON()
								),
							} );
						} }
					/>
					<Button
						variant="ghost"
						size="md"
						onClick={ () => {
							const updatedValues = { ...existingValues };
							delete updatedValues[ key ]; // Remove entry
							onFieldChange( field.id, updatedValues );
						} }
						icon={
							<Trash
								strokeWidth={ 1.5 }
								className="text-icon-secondary"
							/>
						}
					/>
				</div>
			) ) }
			<Button
				variant="outline"
				className="w-fit"
				size="sm"
				onClick={ handleAddNewField }
				icon={ <Plus /> }
			>
				{ __( 'Add New', 'surerank' ) }
			</Button>
		</div>
	);
};

export function renderFieldCommon( {
	field,
	getFieldValue,
	onFieldChange,
	variableSuggestions,
	placeholder = '',
	renderAsGroupComponent = false,
} ) {
	if ( ! field ) {
		return null;
	}

	const currentFieldValue = getFieldValue( field.id ) || field.std || '';

	switch ( field.type ) {
		case 'Select': {
			const options = Array.isArray( field.options )
				? field.options.reduce( ( acc, group ) => {
						if ( group.options ) {
							return { ...acc, ...group.options };
						}
						return acc;
				  }, {} )
				: field.options || {};

			return (
				<div key={ field.id } className="w-full">
					<Select
						size="md"
						value={ currentFieldValue }
						onChange={ ( value ) =>
							onFieldChange( field.id, value )
						}
					>
						<Select.Button />
						<Select.Options className="z-50">
							{ Object.entries( options ).map(
								( [ key, label ] ) => (
									<Select.Option key={ key } value={ key }>
										{ label }
									</Select.Option>
								)
							) }
						</Select.Options>
					</Select>
				</div>
			);
		}

		case 'Group': {
			if ( renderAsGroupComponent ) {
				return (
					<GroupFieldRenderer
						key={ field.id }
						field={ field }
						getFieldValue={ getFieldValue }
						onFieldChange={ onFieldChange }
						variableSuggestions={ variableSuggestions }
					/>
				);
			}

			if ( ! field.fields?.length ) {
				return null;
			}

			return (
				<div key={ field.id } className="space-y-2 w-full">
					<div className="space-y-4 pl-4">
						{ field.fields.map(
							( subField ) =>
								! subField.hidden &&
								subField.type !== 'Hidden' && (
									<div
										key={ subField.id }
										className="flex items-center gap-4"
									>
										{ /* Label, etc. */ }
										{ /* (You could even recursively call renderFieldCommon for subField here) */ }
									</div>
								)
						) }
					</div>
				</div>
			);
		}

		case 'SelectGroup': {
			const groupOptions = Object.values( field?.options || {} );
			return (
				<div key={ field.id } className="w-full">
					<Select
						size="md"
						value={ currentFieldValue }
						onChange={ ( value ) =>
							onFieldChange( field.id, value )
						}
						combobox
						placeholder={ __(
							'Search or select an option',
							'surerank'
						) }
						aria-label={ field.label }
					>
						<Select.Button
							placeholder={ __(
								'Search or select an option',
								'surerank'
							) }
						/>
						<Select.Options>
							{ groupOptions.map( ( group, index ) => (
								<Select.OptionGroup
									key={ index }
									label={ group.label }
								>
									{ Object.entries( group.options ).map(
										( [ key, label ] ) => (
											<Select.Option
												key={ key }
												value={ key }
											>
												{ label }
											</Select.Option>
										)
									) }
								</Select.OptionGroup>
							) ) }
						</Select.Options>
					</Select>
				</div>
			);
		}

		case 'Title': {
			return (
				<div className="w-full">
					<Input
						key={ field.id }
						by="label"
						placeholder={ placeholder }
						defaultValue={ currentFieldValue }
						aria-label={ field.label }
						className="flex-grow max-w-full mdx"
						size="md"
						type="text"
						onChange={ ( value ) => {
							onFieldChange( field.id, value );
						} }
					/>
				</div>
			);
		}

		default:
			return (
				<EditorInput
					key={ field.id }
					by="label"
					trigger="@"
					options={ variableSuggestions }
					placeholder={ placeholder }
					defaultValue={ stringValueToFormatJSON(
						currentFieldValue,
						variableSuggestions,
						'value'
					) }
					onChange={ ( editorState ) => {
						onFieldChange(
							field.id,
							editorValueToString( editorState.toJSON() )
						);
					} }
					className="flex-grow"
					wrapperClassName="[&>ul>li]:capitalize"
					{ ...( WORD_BREAK_ALL_EDITOR_INPUT.includes( field.id ) && {
						style: STYLES_OVERRIDE_FOR_EDITOR_INPUT,
					} ) }
				/>
			);
	}
}

export function renderHelpText( field ) {
	if (
		field?.type === 'Group' ||
		field?.type === 'Select' ||
		field?.type === 'SelectGroup' ||
		field?.id === 'schema_name'
	) {
		return null;
	}

	return (
		/**
		 * @description Help text not shown for schema_name, Group, Select, and SelectGroup fields
		 */
		<Text size={ 14 } weight={ 400 } color="help">
			{ __( 'Type @ to view variable suggestions', 'surerank' ) }
		</Text>
	);
}
