Antiword for docx

a Shawn Rainey/Matt Smith creation.  Original at https://github.com/rainey/antiword-xp-rb

Steve Saus added a few regexes to check for basic formatting like headings, italics, bold, and underlines and have them outputted similar to markdown formatting.  He also made a version of the script (which isn't cleaned up yet and clearly deriviative as all heck) to do the same job with OpenOffice and LibreOffice documents quickly and cleanly.

Tested with Ruby 1.9.3

# What it does:

antiword.rb prints a doc or docx file's text content to stdout. Output has minimal formatting akin to basic markdown and word-wrapped to the console's width.    
Files can be either piped through standard input or by specifying a filename when invoking the script.  
For usage examples, see antiword.rb --help.  
The script will run fastest when given a docx file as an argument, as no temporary files are created this way.  

This is the usage statement you'll get when running with -help or if there's an error in your usage.

	Usage: ./antiword.rb takes a .doc or .docx formatted word document. It can be called either by piping the document to antiword, or by calling antiword.rb
	filename.

	Examples:
	$./antiword.rb < mydoc.doc[x]
	$./antiword.rb mydoc.doc[x]
	$cat mydoc.doc[x] | ./antiword.rb
	$ruby antiword.rb --notimeout -w 100 mydoc.docx

Formatting for heading is done by
` # h1
` # h2
` # h3
and so on, and 
` *italics*
` **bold**
` _underline_

And yes, I know it's not really underlining in markdown.

# How it does it:

The script mostly consists of a word wrapping function, XML replacement regex's, and functionality for interpreting the command line arguments.

If no file is specified in the arguments, we try to read stdin into a temporary file.  If this takes too long (> 5 seconds), we assume the user doesn't know what they're doing, and the usage statement is printed.  Though not documented in the usage statement, there is a --notimeout option that can be added to disable the timeout, which could be needed if you have a really big file or possibly if the file is being streamed over a slow network.  Users are notified of this option if a timeout occurs.  

After the input file is read, we try to read word/document.xml from it using RubyZip's zipfilesystem module.  This module essentially allows us to open files within the zip file as if they were on the file system.  If the unzip completes successfully, read the contents of document.xml into the document string and move on to doing the Regex substitutions.  Otherwise, we try to run the system's antiword with the file and capture its output into the document string.  If that fails, the we recognize the file as an unsupported format, print a usage statement, and exit.  

Since the output of antiword is already formated for the conole, we can skip the regex replacements if that's where we got the output from. Otherwise, we enter a section full of regex replacements.  In this section, we first replace known tags with appropriate strings ('-' for list items, ' | ' to divide colums, "\n" from <w:p> tags, and [pic] to signify a picture).  After that, tags are completely stripped out, leaving only the text of the document behind.  There are 5 XML characters that have escape sequences, and we replace those after removing the tags.  We lastly use ruby's Iconv to replace non-printable UTF-8 characters with their ascii equivalents.

Assuming no errors so far, we write a word-wrapped document to the console.  To do this, we needed to write a word-wrapping function.  Since Ruby allows a programmer to add methods to any existing class, we decided it would be most natural to add the word-wrapping function to the String class.  We also made the function yield lines of a specified width, which allowed us to use a syntax not unlike Ruby's built-in method of iteration when outputting the lines.  The function works basically the same as the one that was done in class.  It breaks each line of a string into words, and progressively adds words to a new string until a word won't fit on the line - in which case, we add that built string to an array of lines, and start building a new one with the word that didn't fit.  We do this until we reach the end of the unwrapped line.  When we reach the end of an unwrapped line, we output a seperator at the end of it, which is "\n" by default.  This ensures paragraph spacing.  

An option of the word wrap function is the seperateSingle parameter, which is set to false by default.  When set to false, the seperator will not be added to any built string that fits on one line.  This prevents things like:

	Wednesday, March 24, 2010
	10:00 - 11:15 am
	H.R. Young Auditorium
	Bey Hall, Room 113

From becoming:

	Wednesday, March 24, 2010

	10:00 - 11:15 am

	H.R. Young Auditorium

	Bey Hall, Room 113

Once the each_wrapped_line function is done collecting wrapped lines, it yields each line in its collection if a block is given, and returns the collection, and we use the block to print the lines.  After that, we delete any temporary file we may have created, and we're done.


Getting back to the arguments logic...
We first flatten the argument back to one string using a randomly generated string to seperate each argument.  We then use some handy regular expressions and lambda functions to look for arguments and deal with them accordingly.  One nice thing about this approach is that the arguments can be supplied by the user in any order.

For more details about any of this, see in-code comments.

Known Issues:
	- Embedded Excel charts are not displayed in the output.
	- Line wrapping does not preserve leading spaces.
	- Timeout does not work in Windows 
	- You cannot pipe a word file in using Windows
	- I have no idea how well my edits work in Windows.  I might have broken the carriage returns.

References/pages we found useful while developing this:

http://www.jackreichert.com/2012/11/09/how-to-convert-docx-to-html/

http://rockhopper.monmouth.edu/cs/jchung/cs498gpl/introduction_to_ruby
http://ruby-doc.org/core/
http://rubyzip.sourceforge.net/
http://www.zenspider.com/Languages/Ruby/QuickRef.html
http://intertwingly.net/blog/2005/08/09/Rails-Confidence-Builder

http://www.robertsosinski.com/2008/12/21/understanding-ruby-blocks-procs-and-lambdas/
http://weblog.raganwald.com/2007/01/closures-and-higher-order-functions.html

http://www.linuxtopia.org/online_books/programming_books/ruby_tutorial/Ruby_Expressions_If_and_Unless_Modifiers.html
http://stackoverflow.com/questions/2203437/how-to-get-linux-console-columns-and-rows-from-php-cli
http://juixe.com/techknow/index.php/2007/01/17/reopening-ruby-classes-2/
http://www.rtslink.com/introductionxmlsoap.html

Sample doc/docx files:
http://openxmldeveloper.org/articles/SampleDocs2.aspx
-Note that the docx files here are actually outdated and won't open with a commercially released version of Word.